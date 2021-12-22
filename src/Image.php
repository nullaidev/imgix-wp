<?php
namespace ImgixWp;

class Image
{
    public static function transformImageUrl(string $url) : string
    {
        if(!empty($url)) {
            if(Core::imgixHost()) {
                $url = \ImgixWp\Image::transformWordPressUploadsUrlToImgixHost($url);
            } elseif(Core::maybeWebpUrls() && !Core::maybeReplaceExtensionWithWebp()) {
                $url = $url . '.webp';
            } elseif(Core::maybeWebpUrls() && Core::maybeReplaceExtensionWithWebp()) {
                $url = substr($url, 0, -strlen(strrchr($url, '.'))) . '.webp';
            }
        }

        return $url;
    }

    public static function transformWordPressUploadsUrlToImgixHost($url, string $folder = 'uploads') : string
    {
        $host = Core::imgixHost();
        $url = str_replace(WP_CONTENT_URL . '/' . $folder, 'https://' . $host, $url);
        $query = Core::defaultImgixQuery();
        return $url . ($query ? "?{$query}" : '');
    }

    /**
     * @param string $size         Get media size such as `full` and `thumbnail`. Set preferred imgix size
     *                             by delimiting with `:`. In example, 'large:full' imgix is `full`.
     * @param callable|null $cb
     *
     * @return mixed|null
     */
    public static function imgSize(string $size = 'full', ?callable $cb = null)
    {
        [$size_normal, $size_imgix] = array_pad(explode(':', $size, 2), 2, null);

        if(Core::imgixHost()) {
            $size = $size_imgix ?? $size_normal ?? null;
        } else {
            $size = $size_normal;
        }

        if(is_callable($cb)) {
            return $cb($size, $size_imgix);
        }

        return $size ?? null;
    }

    /**
     * @param $id
     * @param string $size
     * @param bool $icon
     * @param array $query
     *
     * @return array|bool|null
     */
    public static function getSrc($id, string $size = 'thumbnail', bool $icon = false, array $query = [])
    {
        if(!$id) { return null; }

        $size = static::imgSize($size);

        if(!Core::imgixHost() || (empty($query) && !strpos($size, '?'))) {
            return wp_get_attachment_image_src($id, $size, $icon);
        }

        if(!$src = wp_get_attachment_image_src($id, $size, $icon)) { return $src; }

        if(Core::imgixHost() && strpos($size, '?')) {
            parse_str(substr($size, strpos($size, '?') + 1), $s_query);
            $query = array_merge($s_query, $query);
        }

        $query_str = [];
        foreach ($query as $k => $v) { $query_str[] = $k . '=' . $v; }
        $query_str = implode('&', $query_str);

        $op = '?';
        if(strpos($src[0], '?')) { $op = '&'; }

        return [$src[0] . $op . $query_str, $src[1], $src[2], $src[3] ?? false];
    }

    /**
     * @param $id
     * @param string $size
     * @param bool $icon
     * @param array $attr
     * @param array $query
     *
     * @return string
     */
    public static function getImage($id, string $size, bool $icon = false, array $attr = [], array $query = []) : string
    {
        if(!$id) { return ''; }

        $size = static::imgSize($size);

        if(!Core::imgixHost() || (empty($query) && !strpos($size, '?'))) {
            return wp_get_attachment_image($id, $size, $icon, $attr);
        }

        if(Core::imgixHost() && strpos($size, '?')) {
            parse_str(substr($size, strpos($size, '?') + 1), $s_query);
            $query = array_merge($s_query, $query);
        }

        $query_str = [];
        $srcset = true;
        $attr_from_query = [];
        $scale_up = true;
        $crop = false;
        foreach ($query as $k => $v) {
            $query_str[] = $k . '=' . $v;

            if(in_array($k, ['h', 'w']) && $v < '400') {
                $srcset = false;
            }

            if($k === 'fit' && in_array($v, ['max', 'fillmax', 'min'])) { $scale_up = false; }
            elseif($k === 'fit' && $v === 'crop') { $crop = true; }

            if($k === 'h') { $attr_from_query['height'] = $v; }
            elseif($k === 'w') { $attr_from_query['width'] = $v; }
        }
        $query_str = implode('&', $query_str);
        [$src, $width, $height] = wp_get_attachment_image_src($id, $size, $icon);
        $op = '?';

        $h = intval($attr_from_query['height'] ?? 0);
        $w = intval($attr_from_query['width'] ?? 0);

        if($scale_up) {
            if($h && $height < $h ) { $h = $height; }
            if($w && $width < $w) { $w = $width; }
        }

        if($h && !$w) {
            $w = (int) ($h / $height * $width);
        }

        if($w && !$h) {
            $h = (int) ($w / $width * $height);
        }

        if($h && $height > $width && !$crop) {
            $attr_from_query['height'] = $h;
            $attr_from_query['width'] = (int) ($h / $height * $width);
        }

        if($w && $width > $height && !$crop) {
            $attr_from_query['width'] = $w;
            $attr_from_query['height'] = (int) ($w / $width * $height);
        }

        if($w && $h && $width === $height) {
            $attr_from_query['width'] = $w;
            $attr_from_query['height'] = $h;
        }

        if(strpos($src, '?')) { $op = '&'; }

        $srcset_cb = function($sources) use ($query_str) {
            if(!empty($sources) && is_array($sources)) {
                foreach ( $sources as $i => $source ) {

                    $op = '?';
                    if(strpos($source['url'], '?')) { $op = '&'; }

                    $source['url'] = $source['url'] . $op . $query_str;

                    $sources[$i] = $source;
                }
            }

            return $sources;
        };

        $priority = 678992;

        if($srcset) {
            add_filter( 'wp_calculate_image_srcset', $srcset_cb, $priority);
        } else {
            add_filter( 'wp_get_attachment_metadata', '__return_null', $priority);
        }

        $attr = array_merge($attr, $attr_from_query, ['src' => $src . $op . $query_str]);
        $img = wp_get_attachment_image($id, $size, $icon, $attr);

        if($srcset) {
            remove_filter('wp_calculate_image_srcset', $srcset_cb, $priority);
        } else {
            remove_filter( 'wp_get_attachment_metadata', '__return_null', $priority);
        }

        return $img;
    }
}