Rackspace-cdn-gateway in PHP
============================

Just a nicely interfaced gateway for
[Rackspace CDN SDK](https://github.com/rackerlabs/php-cloudfiles) (which is
deprecated already) written in PHP.

It may be unstable so use on your own risk.

Tested on PHP 5.2.x

Usage
-----

```php
/* This piece of code is probably good candidate for being factored in a Factory
 * to hide the complexity of creating an instance
 * It's here just for demo
 * */
require 'path/to/rackspace/cdn/cloudfiles.php';
$RackspaceAuth = new CF_Authentication('username', 'apikey');
$RackspaceAuth->authenticate();
$RackspaceConnection = new CF_Connection($RackspaceAuth);
$CDN = new Gateways_Rackspace($RackspaceConnection);
$CDN->setConfig( array( /* ..configuration.. */ );
$CDN->container = 'name-of-container';

/* This is how you'd interact with the CDN via factored $CDN object */
try {
  // Create/Update blob
  $CDN->blob('lorem.js')->set('contents', "console.log('hello world!')")->save();

  // Delete blob
  $CDN->blob('lorem.js')->delete();

  // Check if exists
  if( $CDN->blob('lorem.js')->isExists() ) {
    echo 'Blob exists';
  } else {
    echo 'Blob does not exist';
  }

  // Delete container
  $CDN->delete('yes I confirm deletion of current container');

  // Truncate container
  foreach( $CDN->blobs as $blob ) $blob->delete();

  // List all blobs
  $blobs = $CDN->blobs();
  foreach( $blobs as $blob ) {
    echo "Name:     " . $blob->name . "\n";
    echo "Type:     " . $blob->content_type . "\n";
    echo "URI:      " . $blob->uri . "\n";
    echo "SSL URI:  " . $blob->ssl_uri . "\n";
    echo "Contents: " . $blob->contents . "\n";
  }

  // Get Attribute directly by blob name
  echo "Name:    " . $CDN->blob('lorem.js')->name;
  echo "URI:     " . $CDN->blob('lorem.js')->uri;
  echo "SSL URI: " . $CDN->blob('lorem.js')->ssl_uri;
  // etc.

  // Just container info, not really useful imho but it's there
  print_r( $CDN->info() );

} catch( Exception $e ) {
  echo 'Following error occured: ' . $e->getMessage();
}
```
