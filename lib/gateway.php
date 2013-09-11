<?php

// Interface
interface Interfaces_CDNGateway {
  public function info();
  public function blob();
  public function blobs();
  public function delete();
}

// Exceptions
class NoContainerSet extends Exception { }
class NoBlobname extends Exception { }
class NoBlobContent extends Exception { }
class BlobNameIsNotCorrect extends Exception { }
class NoConfirmation extends Exception { }

// Gateway
class Gateways_Rackspace implements Interfaces_CDNGateway {

  private $config = array(
      // TTL in seconds for containers (affects Expire HTTP header of objects
      // newly created)
      // 31557600 - year
      'ttl' => 31557600
    );

  // Instances
  private $connection;
  private $container;

  public function __construct($connection = null) {
    if( !$connection ) {
      throw new NoConnection('Please establish connection to Rackspace API and pass it on');
    }
    $this->connection = $connection;
  }

  public function __get($key) {
    switch( $key ) {
      case 'container':
        return $this->container;
        break;
    }

    return false;
  }

  public function __set($key, $value) {
    switch( $key ) {
      case 'container':
        try {
          $this->container = $this->connection->get_container($value);
        } catch(NoSuchContainerException $e) {
          $this->container = $this->connection->create_container($value);
        }
        $this->container->make_public( $this->config['ttl'] );
        return true;
        break;
    }

    return false;
  }

  public function setConfig($config = array()) {
    // Apply provided configuration
    if( is_array($config) ) $this->config = array_merge($this->config, $config);
  }


  /* API */

  public function info() {
    if( !$this->container ) {
      throw new NoContainerSet('Cannot retrieve info without container');
    }
    list($total_containers, $total_bytes) = $this->connection->get_info();
    return array(
      'total_containers'  => $total_containers,
      'total_bytes'       => $total_bytes,
      'container'         => $this->container
    );
  }

  public function delete($confirmation = '') {
    if( $confirmation !== 'yes I confirm deletion of current container' ) {
      throw new NoConfirmation('Please confirm deletion of container or do not do it');
    }
    if( !$this->container ) {
      throw new NoContainerSet('Set container before deleting it');
    }
    // Delete all blobs inside container
    foreach( $this->blobs() as $blob) $blob->delete();
    // Delete container itself
    $this->connection->delete_container($this->container->name);
    // Reset container instance
    $this->container = null;
  }

  public function blob($name = '') {
    $blob = new Gateways_Rackspace_Blob($name, $this->container);
    return $blob;
  }

  public function blobs() {
    if( !$this->container ) {
      throw new NoContainerSet('Cannot retrieve blobs without container');
    }
    $objects = $this->container->list_objects();
    $blobs = array();
    foreach( $objects as $name ) {
      $blobs[] = new Gateways_Rackspace_Blob($name, $this->container);
    }
    return $blobs;
  }
}

// Single Blob Model
class Gateways_Rackspace_Blob {
  private $gateway,
          $remote_object,
          $fetched,
          $exists,
        // Attributes
          $name,
          $contents,
          $content_type,
          $uri,
          $ssl_uri;

  function __construct($name = null, $gateway = null) {
    $this->name = $name;
    $this->gateway = $gateway;
    return $this;
  }

  function __get($key) {
    return $this->get($key);
  }
  function __set($key, $value) {
    $this->set($key, $value);
    return $this;
  }
  function get($key) {
    $this->fetch();
    return (isset($this->$key) ? $this->$key : null);
  }
  // For chainable operations
  function set($key, $value) {
    $this->fetch();
    $this->$key = $value;
    return $this;
  }

  private function _setUris() {
    $this->uri = $this->gateway->cdn_uri . '/' . $this->name;
    $this->ssl_uri = $this->gateway->cdn_ssl_uri . '/' . $this->name;
  }

  private function _guessContentType() {
    preg_match('/\.([^.]*)$/', $this->name, $matches);
    if( empty($matches) ) {
      throw new BlobNameIsNotCorrect('File extension could not be determined');
    }
    $extension = $matches[1];
    switch( $extension ) {
      case 'js':
        $type = 'application/javascript';
        break;
      case 'css':
        $type = 'text/css';
        break;
      case 'png':
        $type = 'image/png';
        break;
      case 'jpg':
      case 'jpeg':
        $type = 'image/jpeg';
        break;
      default:
        $type = 'text/plain';
        break;
    }
    return $type;
  }

  public function fetch($force = false) {
    // Don't fetch twice unless in FORCE mode
    if( $this->fetched && $force !== true ) return $this;

    if( !$this->name || $this->name == '' ) {
      throw new NoBlobName('Blob should be named');
    }

    try {
      $this->remote_object = $this->gateway->get_object($this->name);
      // Do not override
      if( !$this->contents ) {
        $this->contents = $this->remote_object->read();
      }
      // Do not override
      if( !$this->content_type ) {
        $this->content_type = $this->remote_object->content_type;
      }
      $this->exists = true;
      $this->fetched = true;
      $this->_setUris();
    // Object does not exist
    } catch( NoSuchObjectException $e ) {
      return null;
    // Some other error
    } catch( Exception $e ) {
      return null;
    }

    return $this;
  }

  public function isExists() {
    $this->fetch();
    return $this->exists;
  }

  public function save() {
    if( !$this->contents ) {
      throw new NoBlobContent('Blob cannot be empty on save');
    }

    // Set content type if not done yet
    if( !$this->content_type ) {
      $this->content_type = $this->_guessContentType();
    }

    // Create remote object if not created yet
    if( !$this->isExists() ) {
      $this->remote_object = $this->gateway->create_object($this->name);
    }

    $this->remote_object->content_type = $this->content_type;

    $this->_setUris();
    return $this->remote_object->write($this->contents);
  }

  public function delete() {
    if( $this->isExists() ) {
      return $this->gateway->delete_object($this->name);
    }
    return false;
  }
}
