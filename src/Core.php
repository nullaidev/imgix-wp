<?php
namespace ImgixWp;

class Core
{
    protected static array $options = [
        'admin' => false,
        'imgix_host' => null,
        'imgix_query' => 'auto=format',
        'webp' => true,
        'ext_replace' => true,
    ];

    /**
     * @param array $options
     *
     * admin (bool)             - Default is `false`, set to `true` to allow in admin
     *
     * imgix_host (null|string) - Default is `null`, this is to be set to your imgix
     *                            source URL
     *
     * imgix_query (string)     - Default is `auto=format`, adds query to all imgix
     *                            image URLs.
     *
     * webp (bool)              - Default is `true`, this will rewrite image URLs as
     *                            .webp even when imgix is not used
     *
     * ext_replace (bool)       - Default is `true`, this will replace image extensions
     *                            with .webp but when `false` will append .webp
     *
     * @return void
     */
    public static function init(array $options = []) : void
    {
        static::$options = array_merge(static::$options, $options, array_filter([
            'admin' => defined('IMGIX_WP_ADMIN') ? constant('IMGIX_WP_ADMIN') : null,
            'imgix_host' => defined('IMGIX_WP_IMGIX_HOST') ? constant('IMGIX_WP_IMGIX_HOST') : null,
            'imgix_query' => defined('IMGIX_WP_IMGIX_QUERY') ? constant('IMGIX_WP_IMGIX_QUERY') : null,
            'webp' => defined('IMGIX_WP_WEBP') ? constant('IMGIX_WP_WEBP') : null,
            'ext_replace' => defined('IMGIX_WP_EXT_REPLACE') ? constant('IMGIX_WP_EXT_REPLACE') : null,
        ]));

        if(is_admin()) {
            if(static::$options['admin'] === false) {
                return;
            }
        }

        add_filter( 'wp_get_attachment_image_src', static::class . '::filter_wp_get_attachment_image_src');
        add_filter( 'wp_calculate_image_srcset',  static::class . '::filter_wp_calculate_image_srcset');
    }

    public static function imgixHost() : ?string
    {
        return static::$options['imgix_host'];
    }

    public static function defaultImgixQuery() : string
    {
        return trim(static::$options['imgix_query']);
    }

    public static function maybeReplaceExtensionWithWebp() : bool
    {
        return (bool) static::$options['ext_replace'];
    }

    public static function maybeWebpUrls() : bool
    {
        return (bool) static::$options['webp'];
    }

    public static function filter_wp_get_attachment_image_src($image)
    {
        if(!empty($image) && is_array($image)) {
            $image[0] = Image::transformImageUrl( (string) $image[0]);
        }

        return $image;
    }

    public static function filter_wp_calculate_image_srcset($sources)
    {
        if(!empty($sources) && is_array($sources)) {
            foreach ( $sources as $i => $source ) {
                if(!empty($source) && is_array($source)) {
                    $source['url'] = Image::transformImageUrl((string) $source['url']);
                    $sources[$i] = $source;
                }
            }
        }

        return $sources;
    }
}