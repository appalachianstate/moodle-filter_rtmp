<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *  RTMP streaming media filter plugin
 *
 *  This filter will replace any rtmp links to a media file with
 *  a media plugin that plays that media inline via the core VideoJS media player
 *
 * @package    filter_rtmp
 * @author     Michelle Melton, Fred Woolard (based on mediaplugin filter {@link http://moodle.com})
 * @copyright  2017 Appalachian State University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * RTMP media embedding filter class.
 */

class filter_rtmp extends moodle_text_filter {
    /** @var bool True if currently filtering trusted text */
    private $trusted;

    /**
     * Filter media player code to update for RTMP
     *
     * {@inheritDoc}
     * @see moodle_text_filter::filter()
     */
    public function filter($text, array $options = array()) {
        global $CFG, $PAGE;

        if (!is_string($text) or empty($text)) {
            // Non string data can not be filtered anyway.
            return $text;
        }

        if (stripos($text, '</a>') === false && stripos($text, '</video>') === false && stripos($text, '</audio>') === false) {
            // Performance shortcut - if there are no </a>, </video> or </audio> tags, nothing can match.
            return $text;
        }

        if (stripos($text, 'href="rtmp') === false && stripos($text, 'src="rtmp') === false) {
            // If there are no rtmp sources, do nothing.
            return $text;
        }

        // Check SWF permissions.
        $this->trusted = !empty($options['noclean']) or !empty($CFG->allowobjectembed);

        // Handle all links that contain any 'embeddable' marker text (it could
        // do all links, but the embeddable markers thing should make it faster
        // by meaning for most links it doesn't drop into PHP code).
        if (stripos($text, '</a>')) {
            // Get embed markers from RTMP filter settings.
            $embedmarkers = "";
            if ($CFG->filter_rtmp_enable_audio) {
                $embedmarkers .= "\.mp3";
            }
            if ($CFG->filter_rtmp_enable_video) {
                if (!$embedmarkers == "") {
                    $embedmarkers .= "|";
                }
                $embedmarkers .= "\.flv|\.mp4|\.f4v";
            }
            $regex = '~<a\s[^>]*href="(rtmp:\/\/(?:playlist=[^"]*|[^"]*(?:' . $embedmarkers . '))[^"]*)"[^>]*>([^>]*)</a>~is';
            $text = preg_replace_callback($regex, array($this, 'callback'), $text);
        }

        // Separate text into video, audio and source snippets.
        $matches = preg_split('/(<video[^>]*>)|(<audio[^>]*>)|(<source[^>]*>)/i', $text, -1,
                PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if (!$matches) {
            return $text;
        }

        for ($i = 0; $i < count($matches); $i++) {
            // Filter video tag with RTMP src.
            if (stripos($matches[$i], '<video') !== false && stripos($matches[$i + 1], '<source src="rtmp') !== false) {
                // Capture data-setup config. Make sure gets entire config, even if 2 sets of }.
                $pattern = '/(data-setup[^}]*)(}[^}]*)}/i';
                if (preg_match($pattern, $matches[$i]) == 0) {
                    $pattern = '/(data-setup[^}]*)}/i';
                }

                // Add crossorigin config. Adjust data-setup config:
                // Remove "fluid": true (not compatible with RTMP).
                // Add "width": 400.
                // Add "techOrder": "flash", "html5" (set priority for Flash and HTML5 playback; required for RTMP).
                $replacement = 'crossorigin="anonymous" data-setup="{&quot;language&quot;: &quot;en&quot;, &quot;width&quot;: 400, &quot;techOrder&quot;: [&quot;flash&quot;, &quot;html5&quot;]}';
                $matches[$i] = preg_replace($pattern, $replacement, $matches[$i]);

                // Update type for RTMP in child source code.
                $matches[$i + 1] = str_replace('type="video/', 'type="rtmp/', $matches[$i + 1]);

                // If .mp3 used in video src, update type for RTMP in child source code.
                if (stripos($matches[$i + 1], '.mp3') !== false) {
                    $matches[$i + 1] = str_replace('type="audio/', 'type="rtmp/', $matches[$i + 1]);
                }

                // Replace ampersand character reference with actual '&' in child source code.
                // VideoJS requires this formatting for RTMP playback.
                $matches[$i + 1] = str_replace('&amp;', '&', $matches[$i + 1]);

                // If HLS fallback is set, add iOS source.
                $hlssource = '';
                if ($CFG->filter_rtmp_hls_fallback) {
                    $hlssource = self::get_hls_source($matches[$i + 1]);
                    $matches[$i + 1] .= $hlssource;
                }

                // If closed captions on by default is set, add track code for captions.
                if ($CFG->filter_rtmp_default_cc) {
                    // Use HLS source as base for track code filtering.
                    if ($hlssource == '') {
                        $hlssource = self::get_hls_source($matches[$i + 1]);
                    }
                    $trackcode = self::get_captions($hlssource);
                    $matches[$i + 1] .= $trackcode;
                }
            }

            // Filter audio tag with RTMP src.
            if (stripos($matches[$i], '<audio') !== false && stripos($matches[$i + 1], '<source src="rtmp') !== false) {
                // Capture data-setup config. Make sure gets entire config, even if 2 sets of }.
                $pattern = '/(data-setup[^}]*)(}[^}]*)}/i';
                if (preg_match($pattern, $matches[$i]) == 0) {
                    $pattern = '/(data-setup[^}]*)}/i';
                }

                // Add crossorigin config. Adjust data-setup config:
                // Remove "fluid": true (not compatible with RTMP).
                // Add "width": 400.
                // Add "techOrder": "flash", "html5" (set priority for Flash and HTML5 playback; required for RTMP).
                $replacement = 'crossorigin="anonymous" data-setup="{&quot;language&quot;: &quot;en&quot;, &quot;fluid&quot;: true, &quot;controlBar&quot;: {&quot;fullscreenToggle&quot;: false}, &quot;aspectRatio&quot;: &quot;1:0&quot;, &quot;width&quot;: 400, &quot;techOrder&quot;: [&quot;flash&quot;, &quot;html5&quot;]}';
                $matches[$i] = preg_replace($pattern, $replacement, $matches[$i]);

                // Update type for RTMP in child source code; handles .mp4, .flv, and .f4v used in audio src.
                if (stripos($matches[$i + 1], '.mp4') !== false) {
                    $matches[$i + 1] = str_replace('type="video/', 'type="rtmp/', $matches[$i + 1]);
                }
                if (stripos($matches[$i + 1], '.flv') !== false) {
                    $matches[$i + 1] = str_replace('type="video/', 'type="rtmp/', $matches[$i + 1]);
                    $matches[$i + 1] = str_replace('mp4:', 'flv:', $matches[$i + 1]);
                }
                if (stripos($matches[$i + 1], '.f4v') !== false) {
                    $matches[$i + 1] = str_replace('type="video/', 'type="rtmp/', $matches[$i + 1]);
                }
                if (stripos($matches[$i + 1], '.mp3') !== false) {
                    $matches[$i + 1] = str_replace('type="audio/', 'type="rtmp/', $matches[$i + 1]);
                    $matches[$i + 1] = str_replace('mp4:', 'mp3:', $matches[$i + 1]);
                }

                // Replace ampersand character reference with actual '&' in child source code.
                // VideoJS requires this formatting for RTMP playback.
                $matches[$i + 1] = str_replace('&amp;', '&', $matches[$i + 1]);

                // If HLS fallback is set, add iOS source.
                if ($CFG->filter_rtmp_hls_fallback) {
                    $hlssource = self::get_hls_source($matches[$i + 1]);
                    $matches[$i + 1] .= $hlssource;
                }
            }
        }

        // Concatenate video, audio and source snippets.
        $filteredtext = "";
        foreach ($matches as $match) {
            $filteredtext .= $match;
        }

        return $filteredtext;
    }

    /**
     * Callback routine passed to preg_replace_callback().
     * Replace link with embedded content, if supported.
     *
     * @param   array $matches    Array provided by preg_replace_callback. [0] original text, [1] href attribute, [2] anchor label.
     * @return  string
     */
    private function callback(array $matches) {
        // Check if we ignore it.
        if (preg_match('/class="[^"]*nomediaplugin/i', $matches[0])) {
            return $matches[0];
        }

        // Get name, use default if empty.
        $name = trim($matches[2]);
        if (empty($name)) {
            $name = 'Media Stream (RTMP)';
        }

        // Split provided URL into alternatives.
        list($urls, $options) = self::split_alternatives($matches[1], $width, $height);

        // Trusted if $CFG allowing object embed and 'noclean'
        // was passed to the filter method as an option.
        if ($this->trusted) {
            $options[core_media_manager::OPTION_TRUSTED] = true;
        }

        // We could test whether embed is possible using can_embed, but to save
        // time, let's just embed it with the 'fallback to blank' option which
        // does most of the same stuff anyhow.
        $options[core_media_manager::OPTION_FALLBACK_TO_BLANK] = true;

        // NOTE: Options are not passed through from filter because the 'embed'
        // code does not recognise filter options (it's a different kind of
        // option-space) as it can be used in non-filter situations.
        $result = core_media_manager::instance()->embed_alternatives($urls, $name, $width, $height, $options);

        // If something was embedded, return it, otherwise return original.
        return (empty($result) ? $matches[0] : $result);
    }

    /**
     * Lifted from lib/medialib.php. Need to omit the call to clean_param
     * until 'rtmp' is added as a valid scheme in the Moodle core libs.
     *
     * @param   string  $combinedurl    String of 1 or more alternatives separated by #
     * @param   int     $width          Output variable: width (will be set to 0 if not specified)
     * @param   int     $height         Output variable: height (0 if not specified)
     * @return  array                   Containing two elements, an array of 1 or more moodle_url objects,
     *                                  and an array of names (optional)
     * @uses    $DB, $COURSE, $CFG
     */
    private static function split_alternatives($combinedurl, &$width, &$height) {
        global $DB, $COURSE, $CFG;

        $origurls    = array_map('trim', explode('#', $combinedurl));
        $width        = 0;
        $height       = 0;
        $clipurls    = array();
        $clipnames   = array();
        $options      = array();

        // First pass through the array to expand any playlist entries
        // and look for height-width parameters.
        $expandedlist = array();

        foreach ($origurls as $url) {
            $matches = null;

            if (preg_match('/^rtmp:\/\/playlist=(.+)/', $url, $matches)) {
                // The HTML editor content (where URLs with which we are
                // concerned are placed) is massaged, converting ampersands.
                // We need to put them back to match the playlist name.
                $playlistrecord = self::get_playlist($COURSE->id, htmlspecialchars_decode($matches[1]));
                if (!$playlistrecord) {
                    continue;
                }

                foreach (explode("\n", $playlistrecord->list) as $listitem) {
                    $expandedlist[] = trim($listitem);
                }

                // With a playlist, do not want the page littered
                // with links while waiting for the JavaScript to
                // replace them with the media player.
                $options[core_media_manager::OPTION_NO_LINK] = true;
            } else if (preg_match('/^d=([\d]{1,4})x([\d]{1,4})$/i', $url, $matches)) {
                // You can specify the size as a separate part of the array like
                // #d=640x480 without actually including as part of a url.
                $width  = $matches[1];
                $height = $matches[2];
                continue;
            } else {
                // Append as is.
                $expandedlist[] = $url;
            }
        } // foreach - first pass

        // Second pass, massage the URLs and parse any height or width
        // or clip name or close caption directive.
        foreach ($expandedlist as $listitem) {
            // First parse using comma delimter to separate playlist
            // names (if present) from the URL.
            @list($listitemurl, $listitemname) = array_map('trim', explode(',', $listitem, 2));

            // Clean up url. But first substitute the rtmp scheme with
            // http to allow validation against everything else, then
            // put the rtmp back.
            $listitemurl = preg_replace('/^rtmp:\/\//i', 'http://', $listitemurl, 1);
            $listitemurl = clean_param($listitemurl, PARAM_URL);
            if (empty($listitemurl)) {
                continue;
            }
            $listitemurl = preg_replace('/^http:\/\//', 'rtmp://', $listitemurl, 1);

            // Turn it into moodle_url object.
            $clipurls[]    = new moodle_url($listitemurl);
            $clipnames[]   = empty($listitemname) ? '' : htmlspecialchars_decode($listitemname);
        } // foreach - second pass

        $options['PLAYLIST_NAMES'] = $clipnames;
        return array($clipurls, $options);
    }

    /**
     * Get RTMP formatted HLS source for VideoJS.
     *
     * @access private
     * @static
     *
     * @param   string    $source
     * @return  string    RTMP formatted HLS source code
     *
     * @uses $CFG
     */
    private static function get_hls_source($source) {
        global $CFG;

        // Get RTMP formatted source, update src for HLS.
        $hlssource = $source;
        $hlssource = str_replace('src="rtmp', 'src="http', $hlssource);
        $hlssource = str_replace('&', '_definst_/', $hlssource);

        // Prepare HLS URL style based on configured setting.
        switch ($CFG->filter_rtmp_hls_urlfmt) {
            // Adobe Media Server.
            case 'fms':
                $hlsurl = ".m38u";
                break;
                // Wowza Streaming Engine (wse).
            default:
                $hlsurl = "/playlist.m3u8";
        }

        // Format HLS .mp4 src URL with corresponding server format.
        if (stripos($hlssource, '.mp4') !== false) {
            $hlssource = str_replace('type="rtmp/', 'type="video/', $hlssource);
            $hlssource = str_replace('.mp4', '.mp4' . $hlsurl, $hlssource);
        }

        // Format HLS .flv src URL with mp4 MIME type and corresponding server format.
        if (stripos($hlssource, '.flv') !== false) {
            $hlssource = str_replace('flv:', 'mp4:', $hlssource);
            $hlssource = str_replace('.flv', '.flv' . $hlsurl, $hlssource);
            $hlssource = str_replace('type="rtmp/x-flv', 'type="video/mp4', $hlssource);
        }

        // Format HLS .f4v src URL with corresponding server format.
        if (stripos($hlssource, '.f4v') !== false) {
            $hlssource = str_replace('.f4v', '.f4v' . $hlsurl, $hlssource);
            $hlssource = str_replace('type="rtmp/', 'type="video/', $hlssource);
        }

        // Format HLS .mp3 src URL with corresponding server format and audio type.
        if (stripos($hlssource, '.mp3') !== false) {
            $hlssource = str_replace('.mp3', '.mp3' . $hlsurl, $hlssource);
            $hlssource = str_replace('type="rtmp/', 'type="audio/', $hlssource);
        }

        return $hlssource;
    }

    /**
     * Get RTMP formatted track code for VideoJS.
     *
     * @access private
     * @static
     *
     * @param   string    $hlssource
     * @return  string    RTMP formatted track code
     *
     * @uses $CFG
     */
    private static function get_captions($hlssource) {
        global $CFG;

        // Prepare HLS URL style based on configured setting.
        switch ($CFG->filter_rtmp_hls_urlfmt) {
            // Adobe Media Server.
            case 'fms':
                $hlsurl = ".m38u";
                break;
                // Wowza Streaming Engine (wse).
            default:
                $hlsurl = "/playlist.m3u8";
        }

        // Get VideoJS formatted HLS source, update src for WebVTT captions (VideoJS).
        $captionfile = str_replace('<source src="', '', $hlssource);
        if (stripos($captionfile, '.mp4') !== false) {
            $captionfile = str_replace('.mp4' . $hlsurl . '" type="video/mp4" />', '.vtt', $captionfile);
            $captionfile = str_replace('mp4:', '', $captionfile);
        }
        if (stripos($captionfile, '.flv') !== false) {
            $captionfile = str_replace('.flv' . $hlsurl . '" type="video/mp4" />', '.vtt', $captionfile);
            $captionfile = str_replace('mp4:', '', $captionfile);
        }
        if (stripos($captionfile, '.f4v') !== false) {
            $captionfile = str_replace('.f4v' . $hlsurl . '" type="video/mp4" />', '.vtt', $captionfile);
            $captionfile = str_replace('mp4:', '', $captionfile);
        }
        if (stripos($captionfile, '.mp3') !== false) {
            $captionfile = str_replace('.mp3' . $hlsurl . '" type="audio/mp3" />', '.vtt', $captionfile);
            $captionfile = str_replace('mp3:', '', $captionfile);
        }

        // Create track code with corresponding src URL, add to end of sources.
        $trackcode = '<track kind="captions" src="' . $captionfile . '" srclang="en" label="English" default="">';
        return $trackcode;
    }

    /**
     * Fetch a playlist entry
     *
     * @access private
     * @static
     *
     * @param   int       $courseid
     * @param   string    $name
     * @return  mixed     Playlist record (object) or false if not found
     *
     * @uses $DB
     */
    private static function get_playlist($courseid, $name) {
        global $DB;
        static $cache = array();

        $key = "{$courseid}:{$name}";
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $cache[$key] = $DB->get_record('playlist', array('course' => $courseid, 'name' => $name));
            return $cache[$key];
        } catch (Exception $exc) {
            // Squelch it, assume playlist table not present.
            return false;
        }
    }
}
