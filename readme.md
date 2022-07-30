## imgix for WordPress

This composer package contains a few helper methods for working with [imgix](https://imgix.com/) and [WordPress](https://wordpress.org/). This tooling is helpful if you use the WordPress image functions like `wp_get_attachment_image()` and want your URLs rewritten to imgix URLs.

Further, this package can also rewrite your image URLs to [WebP](https://developers.google.com/speed/webp/docs/using) versions if you are not using imgix. Note, this package does not generate WebP images it only rewrites them to WebP if you are not using imgix. If you are using imgix, use [imgix to serve WebP](https://docs.imgix.com/tutorials/improved-compression-auto-content-negotiation) images.

### Install

In your WordPress theme or plugin.

```bash
composer require nullaidev/imgix-wp
```

Be sure you include the composer's `autoload.php`.

```php
// Composer
if(file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}
```

### Initialize

In your themes `functions.php` file call `\ImgixWp\Core::init()`. When no options are used all image URLs created with `wp_get_attachment_image_src()`, `wp_get_attachment_image()`, and `wp_calculate_image_srcset()` will be rewritten as WebP versions.

```php
// functions.php
\ImgixWp\Core::init();
```

*IMPORTANT*: If you don't have WebP versions of your images on your server calling `\ImgixWp\Core::init()` will break your website images leading to 404 errors. 

### Generate WebP Images

You can generate your own WebP images in WordPress using the `cwebp` command. 

1. First, [download and install the cwebp CLI tool](https://developers.google.com/speed/webp/docs/precompiled).
2. From the command line go to the folder with your images such as `wordpress/wp-content/uploads/{year}/{month}`.
3. Run the following command.
4. Enjoy your webp images.

```bash
# Example from path, wordpress/wp-content/uploads/2021/01
for file in *; do cwebp -q 50 "$file" -o "${file%.*}.webp"; done
```

### imgix Activation

In your themes `functions.php` file call `\ImgixWp\Core::init()` and provide your imgix source URL host name (exclude `https`). The imix source must point to your `wp-content/uploads` folder.

```php
// functions.php
\ImgixWp\Core::init([
    'imgix_host' => 'your-source-name-here.imgix.net'
]);
```

Calling the `\ImgixWp\Core::init()` method will automatically rewrite all of your image URLs to the imgix URL generated by `wp_get_attachment_image_src()`, `wp_get_attachment_image()`, and `wp_calculate_image_srcset()` when they are using on the front-end of your WordPress website.

Raw URLs within content will not be rewritten to an imgix version.

### Helper Functions

To apply the special query parameters to an image when using `wp_get_attachment_image()` you can use `\ImgixWp\Image::getImage()` instead. The `\ImgixWp\Image::getImage()` has the same signature as `wp_get_attachment_image()` but include another parameter for the query.

For example, to get WebP images served by imgix using the query string `?auto=format` you can use the following:

```php
// page.php
$attachment_id = 1;

// WordPress version - ?auto=format is NOT applied
echo wp_get_attachment_image($attachment_id, 'full', false, []);

// imgix version - ?auto=format is applied
echo \ImgixWp\Image::getImage($attachment_id, 'full', false, [], [
    'auto' => 'format'
]);
```
