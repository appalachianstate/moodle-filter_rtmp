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

    /** @var string video data-setup */
    private $videodatasetup;

    /** @var string audio data-setup */
    private $audiodatasetup;

    /** @var string http or https */
    private $protocol;

    /** @var string hls fallback enabled */
    private $hlsfallback;

    /** @var string hls url style */
    private $hlsurl;

    /** @var string captions enabled */
    private $defaultcc;

    /** @var string audio filtering enabled */
    private $enableaudio;

    /** @var string video filtering enabled */
    private $enablevideo;

    /** @var string limit size */
    private $limitsize;

    /** @var string default player width */
    private $defaultwidth;

    /** @var string default player height */
    private $defaultheight;

    /** @var string video css classes */
    private $videoclass;

    /** @var string audio css classes */
    private $audioclass;

    /**
     * Filter media player code to update for RTMP
     *
     * {@inheritDoc}
     * @see moodle_text_filter::filter()
     */
    public function filter($text, array $options = array()) {
        global $CFG;

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

        self::get_config();

        // Handle all links that contain any 'embeddable' marker text.
        // (It could do all links, but the embeddable markers thing should make
        // it faster by meaning for most links it doesn't drop into PHP code).
        if (stripos($text, '</a>')) {
            // Get embed markers from RTMP filter settings.
            $embedmarkers = " ";

            if ($this->enableaudio) {
                $embedmarkers .= "|\.mp3";
            }

            if ($this->enablevideo) {
                $embedmarkers .= "|\.flv|\.mp4|\.f4v";
            }

            // Regex gets string from starting <a tag to closing </a> tag for rtmp single video and playlists links.
            $regex = '~<a\s[^>]*href="(rtmp:\/\/(?:playlist=[^"]*|[^"]*(?:' . $embedmarkers . '))[^"]*)"[^>]*>([^>]*)</a>~is';
            $newtext = preg_replace_callback($regex, array($this, 'callback'), $text);

            if ($newtext == $text || is_null($text)) {
                return $text;
            }

            // Set changed text to text, so filter can continue work on modifiled <a tags or already set <video or <audio tags.
            $text = $newtext;
        }

        $matches = preg_split('/(<video[^>]*>)|(<audio[^>]*>)|(<source[^>]*>)/i', $text,
                -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if (!$matches) {
            return $text;
        }

        $mediatag = '';
        $dimensions = array();

        // Format <video, <audio and <source code.
        for ($i = 0; $i < count($matches); $i++) {
            if (preg_match('/max-width:(\d*)px/i', $matches[$i], $dimensions) === 1) {
                // If limit size is not enabled, set outer div to maximum width.
                if ($this->limitsize != 1) {
                    $matches[$i] = preg_replace('/max-width:(\d*)px/i', 'max-width:100%', $matches[$i]);
                }

                // Set individual video or audio dimensions if configured and limit size is enabled.
                if ($this->limitsize == 1 && $dimensions[1] != $this->defaultwidth) {
                    $this->videodatasetup = str_replace($this->defaultwidth, $dimensions[1], $this->videodatasetup);
                    $this->defaultwidth = $dimensions[1];
                }
            }

            // Change video with and height data-setup config if set for individiual video.
            if (stripos($matches[$i], '<video') !== false) {
                if (preg_match('/width="(\d*)" height="(\d*)"/i', $matches[$i], $dimensions) === 1) {
                    $this->videodatasetup = str_replace($this->defaultwidth, $dimensions[1], $this->videodatasetup);
                    $this->videodatasetup = str_replace($this->defaultheight, $dimensions[2], $this->videodatasetup);
                    $this->defaultwidth = $dimensions[1];
                    $this->defaultheight = $dimensions[2];
                }

                // Store <video code to let child <source know if <track should be added (video player).
                $mediatag = self::format_video($matches[$i]);
                $matches[$i] = $mediatag;
            }

            if (stripos($matches[$i], '<audio') !== false) {
                // Store <audio code to let child <source know if <track should be added (playlist - video player).
                $mediatag = self::format_audio($matches[$i]);
                $matches[$i] = $mediatag;
            }

            if (stripos($matches[$i], '<source') !== false) {
                if (($this->enablevideo && preg_match('/(.mp4|.flv|.f4v)/i', $matches[$i]) === 1)
                        || ($this->enableaudio && preg_match('(.mp3)', $matches[$i]) === 1)) {
                    // Format RTMP URL for VideoJS - add & and MIME type.
                    $matches[$i] = self::format_url($matches[$i]);

                    // If HLS fallback is set, add iOS source.
                    $hlssource = self::get_hls_source($matches[$i]);
                    if ($this->hlsfallback && stripos($matches[$i], '.flv') === false) {
                        // FLV is not supported by iOS.
                        $matches[$i] .= $hlssource;
                    }

                    // If closed captions on by default is set and parent is <video tag, add track code for captions.
                    if ($this->defaultcc && (stripos($mediatag, '<video') !== false || stripos($mediatag, 'playlist') !== false)) {
                        // Use HLS source as base for track code filtering.
                        $trackcode = self::get_captions($hlssource);
                        $matches[$i] .= $trackcode;
                    }
                }
            }

            // Reset $mediatag for next video or audio code.
            if (stripos($matches[$i], '</video') !== false || stripos($matches[$i], '</audio') !== false) {
                $mediatag = '';
            }
        }

        // Concatenate <video, <audio and <source snippets.
        $filteredtext = "";
        foreach ($matches as $match) {
            $filteredtext .= $match;
        }

        // If filtered text has any playlist content, prepare to format it.
        if (preg_match('/(<video|<audio)[^>]*class="[^"]*\splaylist/i', $filteredtext) === 1) {
            // Reset $matches.
            $matches = array();

            // Separate text into <video></video> and <audio></audio> chunks to tag playlists and format.
            $matches = preg_split('/(<video[\w\W]+?<\/video>)|(<audio[\w\W]+?<\/audio>)/i', $filteredtext, -1,
                    PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            if (!$matches) {
                return $filteredtext;
            }

            // Format playlist designated text for playlist.
            for ($i = 0; $i < count($matches); $i++) {
                if (preg_match('/(<video|<audio)[^>]*class="[^"]*\splaylist/i', $matches[$i]) == 1) {
                    $newmatch = self::format_for_playlist($matches[$i]);

                    // If <video or <audio code was modified for playlist, continue formatting for playlist.
                    if ($newmatch != $matches[$i]) {
                        $matches[$i] = $newmatch;
                        // Remove ending </div>s from $matches[$i + 1] - moved to $matches[$i] in playlist formatting.
                        if ($this->limitsize) {
                            // If limit size is enabled, there will be an extra wrapping div for max-width setting.
                            $matches[$i + 1] = str_replace('</div></div>', '', $matches[$i + 1]);
                        } else {
                            $matches[$i + 1] = str_replace('</div>', '', $matches[$i + 1]);
                        }
                    }
                }
            }

            // Concatenate video and audio snippets.
            $filteredtext = "";
            foreach ($matches as $match) {
                $filteredtext .= $match;
            }
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

        // If snippet has more than one source, it is probably a playlist.
        // Add playlist class so we can format later.
        if (substr_count($result, '<source') > 1) {
            if (stripos($result, '<video') !== false) {
                $result = str_replace('class="' . $this->videoclass, 'class="' . $this->videoclass . ' playlist', $result);
            } else {
                $result = str_replace('class="' . $this->audioclass, 'class="' . $this->audioclass . ' playlist', $result);
            }
        }

        // Get playlist names (if provided), or filenames (if not).
        if (preg_match('/(<video|<audio)[^>]*class="[^"]*\splaylist/i', $result) == 1) {
            $sources = preg_split('/(<source[^>]*>)/i', $result, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            if ($sources) {
                for ($i = 0, $j = 0; $i < count($sources); $i++) {
                    if (stripos($sources[$i], '<source') !== false) {
                        if ($options['PLAYLIST_NAMES'][$j] == '') {
                            // If array of playlist names is empty, get filename.
                            $filename = array();
                            $path = $urls[$j]->get_path();
                            preg_match('/([^\/]*)(.mp3|.mp4|.flv|.f4v)/i', $path, $filename);
                            $title = $filename[0];
                        } else {
                            // Otherwise get provided name.
                            $title = $options['PLAYLIST_NAMES'][$j];
                        }

                        // Add a title attribute to the <source code with the name, so it can be displayed later.
                        $sources[$i] = str_replace('" />', '" title="' . $title . '" />', $sources[$i]);
                        $j++;
                    }
                }
            }

            // Concatenate source snippets.
            $resultwithtitles = "";
            foreach ($sources as $source) {
                $resultwithtitles .= $source;
            }

            $result = $resultwithtitles;
        }

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
    private function split_alternatives($combinedurl, &$width, &$height) {
        global $DB, $COURSE, $CFG;

        $origurls    = array_map('trim', explode('#', $combinedurl));
        $width       = 0;
        $height      = 0;
        $clipurls    = array();
        $clipnames   = array();
        $options     = array();

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
     * Get media player and filter configs.
     *
     * @access private
     */
    private function get_config() {
        global $CFG;

        // Get and verify audio config.
        $this->enableaudio = $CFG->filter_rtmp_enable_audio;
        if (!is_numeric($this->enableaudio) || $this->enableaudio < 0) {
            $this->enableaudio = 1;
        }

        // Get and verify video config.
        $this->enablevideo = $CFG->filter_rtmp_enable_video;
        if (!is_numeric($this->enablevideo) || $this->enablevideo < 0) {
            $this->enablevideo = 1;
        }

        // Get and verify limit size config.
        $this->limitsize = get_config('media_videojs', 'limitsize');
        if (!is_numeric($this->limitsize) || $this->limitsize < 0) {
            $this->limitsize = 1;
        }

        // Get and verify default width and height settings.
        $this->defaultwidth = $CFG->media_default_width;
        if (!is_numeric($this->defaultwidth) || $this->defaultwidth <= 0) {
            $this->defaultwidth = 400;
        }

        $this->defaultheight = $CFG->media_default_height;
        if (!is_numeric($this->defaultheight) || $this->defaultheight <= 0) {
            $this->defaultheight = 300;
        }

        // Set VideoJS video data-setup values.
        // Remove "fluid": true (not compatible with RTMP).
        // Add width setting.
        // Add height setting.
        // Add "techOrder": "flash", "html5" (set priority for Flash and HTML5 playback; required for RTMP).
        $this->videodatasetup = 'data-setup="{&quot;language&quot;: &quot;en&quot;, '
            . '&quot;techOrder&quot;: [&quot;flash&quot;, &quot;html5&quot;]}"';

        // Set VideoJS audio data-setup values.
        // Add width setting.
        // Add "techOrder": "flash", "html5" (set priority for Flash and HTML5 playback; required for RTMP).
        $this->audiodatasetup = 'data-setup="{&quot;language&quot;: &quot;en&quot;, &quot;fluid&quot;: true, '
            . '&quot;controlBar&quot;: {&quot;fullscreenToggle&quot;: false}, &quot;aspectRatio&quot;: &quot;1:0&quot;, '
            . '&quot;techOrder&quot;: [&quot;flash&quot;, &quot;html5&quot;]}"';

        // Add width/height data-setup config if limit size is enabled.
        if ($this->limitsize) {
            $this->videodatasetup = str_replace('}"', ', &quot;width&quot;: ' . $this->defaultwidth . ', &quot;height&quot;: '
                . $this->defaultheight . '}"', $this->videodatasetup);
            $this->audiodatasetup = str_replace('}"', ', &quot;width&quot;: ' . $this->defaultwidth . '}"', $this->audiodatasetup);
        }

        // Get and verify HTTPS config.
        $https = $CFG->filter_rtmp_https;
        if (!is_numeric($https) || $https < 0) {
            $https = 0;
        }
        $this->protocol = 'http';
        if ($https) {
            $this->protocol = 'https';
        }

        // Get and verify HLS fallback config.
        $this->hlsfallback = $CFG->filter_rtmp_hls_fallback;
        if (!is_numeric($this->hlsfallback) || $this->hlsfallback < 0) {
            $this->hlsfallback = 0;
        }

        // Prepare HLS URL style based on configured setting.
        switch ($CFG->filter_rtmp_hls_urlfmt) {
            // Adobe Media Server.
            case 'fms':
                $this->hlsurl = ".m3u8";
                break;
                // Wowza Streaming Engine (wse).
            default:
                $this->hlsurl = "/playlist.m3u8";
        }

        // Get and verify closed captions config.
        $this->defaultcc = $CFG->filter_rtmp_default_cc;
        if (!is_numeric($this->defaultcc) || $this->defaultcc < 0) {
            $this->defaultcc = 0;
        }

        // Get and verify video and audio CSS class config.
        // VideoJS needs video-js class to display properly.
        // See https://tracker.moodle.org/browse/MDL-58674.
        $this->videoclass = get_config('media_videojs', 'videocssclass');
        if (preg_match('/(\svideo-js|video-js\s|\bvideo-js\b)/i', $this->videoclass) !== 1) {
            $this->videoclass .= ' video-js';
            set_config('videocssclass', $this->videoclass, 'media_videojs');
        }

        $this->audioclass = get_config('media_videojs', 'audiocssclass');
        if (preg_match('/(\svideo-js|video-js\s|\bvideo-js\b)/i', $this->audioclass) !== 1) {
            $this->audioclass .= ' video-js';
            set_config('audiocssclass', $this->audioclass, 'media_videojs');
        }

        // Add VideoJS responsive class if it has not already been added and limit size is not enabled (creates maximum width player).
        if ($this->limitsize != 1 && preg_match('/(\svjs-16-9|vjs-16-9\s|\bvjs-16-9\b)/i', $this->videoclass) !== 1) {
            $this->videoclass .= ' vjs-16-9';
            set_config('videocssclass', $this->videoclass, 'media_videojs');
        }
    }

    /**
     * Format video code for VideoJS RTMP.
     *
     * @access private
     *
     * @param string $match
     * @return string video code
     */
    private function format_video($match) {
        // Add crossorigin config. Adjust data-setup config.
        $replacement = 'crossorigin="anonymous" ' . $this->videodatasetup;

        // Moodle 3.2.4 changed to data-setup-lazy which does not work with RTMP.
        if (preg_match('/(data-setup-lazy="[^"]*")/i', $match) == 1) {
            return preg_replace('/(data-setup-lazy="[^"]*")/i', $replacement, $match);
        }

        return preg_replace('/(data-setup="[^"]*")/i', $replacement, $match);
    }

    /**
     * Format audio code for VideoJS RTMP.
     *
     * @access private
     *
     * @param string $match
     * @return string audio code
     */
    private function format_audio($match) {
        // Add crossorigin config. Adjust data-setup config.
        $replacement = 'crossorigin="anonymous" ' . $this->audiodatasetup;

        // Moodle 3.2.4 changed to data-setup-lazy which does not work with RTMP.
        if (preg_match('/(data-setup-lazy="[^"]*")/i', $match) == 1) {
            return preg_replace('/(data-setup-lazy="[^"]*")/i', $replacement, $match);
        }

        return preg_replace('/(data-setup="[^"]*")/i', $replacement, $match);
    }

    /**
     * Format URL for VideoJS RTMP.
     * Add & and MIME type before username.
     * Change type to rtmp.
     * Remove '+' for spaces if needed.
     *
     * @access private
     *
     * @param string $source
     * @return string RTMP formatted URL for VideoJS
     */
    private function format_url($source) {
        // Pattern includes everything from beginning of URL through 3 slashes.
        // Assumes src URL is formatted 'rtmp://streamingurl/appname/username/...'.
        // Changes to URL formatted for VideoJS RTMP: 'rtmp://streamingurl/appname/&mp4:username/...'.
        // Changes MIME type to rtmp.
        $pattern = '/([^\/]*)\/\/([^\/]*)\/([^\/]*)\//i';
        $spliturl = array();

        if (stripos($source, '.mp4') !== false || stripos($source, '.f4v') !== false) {
            preg_match($pattern, $source, $spliturl);
            $source = preg_replace($pattern, $spliturl[0] . '&mp4:', $source);
            $source = str_replace('type="video/', 'type="rtmp/', $source);
        }

        if (stripos($source, '.flv') !== false) {
            preg_match($pattern, $source, $spliturl);
            $source = preg_replace($pattern, $spliturl[0] . '&flv:', $source);
            $source = str_replace('type="video/', 'type="rtmp/', $source);
        }

        if (stripos($source, '.mp3') !== false) {
            preg_match($pattern, $source, $spliturl);
            $source = preg_replace($pattern, $spliturl[0] . '&mp3:', $source);
            $source = str_replace('type="audio/', 'type="rtmp/', $source);

            // If '+' in file path, change mp3 type to mp4 for VideoJS compatibility.
            if (stripos($source, '+') !== false) {
                $source = str_replace('rtmp/mp3', 'rtmp/mp4', $source);
            }
        }

        // Replace '+' or '%20' in file path with space for VideoJS compatibility.
        if (stripos($source, '+') !== false) {
            $source = str_replace('+', ' ', $source);
        }

        if (stripos($source, '%20') !== false) {
            $source = str_replace('%20', ' ', $source);
        }

        return $source;
    }

    /**
     * Get RTMP formatted HLS source for VideoJS.
     *
     * @access private
     *
     * @param   string    $source
     * @return  string    RTMP formatted HLS source code
     *
     * @uses $CFG
     */
    private function get_hls_source($source) {
        global $CFG;

        // Use RTMP formatted source to update for HLS.
        $hlssource = $source;
        $hlssource = str_replace('src="rtmp', 'src="' . $this->protocol, $hlssource);

        if (stripos($this->hlsurl, 'playlist.m3u8') !== false) {
            // Wowza HLS format.
            $hlssource = str_replace('&', '_definst_/', $hlssource);
        } else {
            // FMS HLS format.
            $hlssource = str_replace('&', '', $hlssource);
        }

        // Format HLS .mp4 src URL with corresponding server format.
        if (stripos($hlssource, '.mp4') !== false) {
            $hlssource = str_replace('type="rtmp/', 'type="video/', $hlssource);
            $hlssource = str_replace('.mp4', '.mp4' . $this->hlsurl, $hlssource);
        }

        // Format HLS .flv src URL with mp4 MIME type and corresponding server format.
        // Only included for use in formatting closed caption track source later.
        if (stripos($hlssource, '.flv') !== false) {
            $hlssource = str_replace('flv:', 'mp4:', $hlssource);
            $hlssource = str_replace('.flv', '.flv' . $this->hlsurl, $hlssource);
            $hlssource = str_replace('type="rtmp/x-flv', 'type="video/mp4', $hlssource);
        }

        // Format HLS .f4v src URL with corresponding server format.
        if (stripos($hlssource, '.f4v') !== false) {
            $hlssource = str_replace('.f4v', '.f4v' . $this->hlsurl, $hlssource);
            $hlssource = str_replace('type="rtmp/', 'type="video/', $hlssource);
        }

        // Format HLS .mp3 src URL with corresponding server format and audio type.
        if (stripos($hlssource, '.mp3') !== false) {
            $hlssource = str_replace('.mp3', '.mp3' . $this->hlsurl, $hlssource);
            $hlssource = str_replace('type="rtmp/', 'type="audio/', $hlssource);
        }

        return $hlssource;
    }

    /**
     * Get RTMP formatted track code for VideoJS.
     *
     * @access private
     *
     * @param   string    $hlssource
     * @return  string    RTMP formatted track code
     *
     * @uses $CFG
     */
    private function get_captions($hlssource) {
        global $CFG;

        // Use VideoJS formatted HLS source to update src for WebVTT captions.
        $captionfile = str_replace('<source src="', '', $hlssource);

        if (stripos($captionfile, '.mp4') !== false) {
            $captionfile = preg_replace('/(\.mp4[^>]*>)/i', '.vtt', $captionfile);
            $captionfile = str_replace('mp4:', '', $captionfile);
        }

        if (stripos($captionfile, '.flv') !== false) {
            $captionfile = preg_replace('/(\.flv[^>]*>)/i', '.vtt', $captionfile);
            $captionfile = str_replace('mp4:', '', $captionfile);
        }

        if (stripos($captionfile, '.f4v') !== false) {
            $captionfile = preg_replace('/(\.f4v[^>]*>)/i', '.vtt', $captionfile);
            $captionfile = str_replace('mp4:', '', $captionfile);
        }

        if (stripos($captionfile, '.mp3') !== false) {
            $captionfile = preg_replace('/(\.mp3[^>]*>)/i', '.vtt', $captionfile);
            $captionfile = str_replace('mp3:', '', $captionfile);
        }

        // If Adobe FMS, add vtt subdirectory to src.
        if (stripos($this->hlsurl, 'playlist.m3u8') === false) {
            $filename = array();
            if (preg_match('/\/[^\/]*\.vtt$/i', $captionfile, $filename)) {
                $captionfile = preg_replace('/\/[^\/]*\.vtt$/i', '/vtt' . $filename[0], $captionfile);
            }
        }

        // Create track code with corresponding src URL, add to end of sources.
        $trackcode = '<track kind="captions" src="' . $captionfile . '" srclang="en" label="English" default="">';
        return $trackcode;
    }

    /**
     * Format code for playlist for VideoJS.
     *
     * @access private
     *
     * @param   string    $filteredtext
     * @return  string    playlist formatted code
     *
     * @uses $PAGE
     */
    private function format_for_playlist($filteredtext) {
        global $PAGE;

        // Get and pass protocol, HLS fallback, HLS URL, and closed caption arguments to Javascript module.
        $arguments = array('httprotocol' => $this->protocol, 'hlsfallback' => $this->hlsfallback, 'hlsurl' => $this->hlsurl,
            'defaultcc' => $this->defaultcc);
        $PAGE->requires->js_call_amd('filter_rtmp/videojs_playlist', 'initialize', array($arguments));

        // Split text into <video, <audio, <source, <track and </video> snippets.
        $matches = preg_split('/(<video[^>]*>)|(<audio[^>]*>)|(<source[^>]*>)|(<track[^>]*>)|(<\/video[^>]*>)/i',
                $filteredtext, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if (!$matches) {
            return $filteredtext;
        }

        $playlisttracks = array();
        $mediaid = array();
        $sources = 0;
        $tracks = 0;

        for ($i = 0, $j = 0; $i < count($matches); $i++) {
            // Format <video code to append '-video-playlist' to id, add playlist and HLS classes.
            if (stripos($matches[$i], '<video') !== false) {
                preg_match('/(id_[^"]*)/i', $matches[$i], $mediaid);
                $matches[$i] = preg_replace('/(id="[^"]*)/i', 'id="' . $mediaid[0] . '-video-playlist', $matches[$i]);
                $matches[$i] = str_replace('class="', 'class="video-playlist ', $matches[$i]);
            }

            // Format <audio code to <video for playlists (works better for playlist).
            // Append '-video-playlist' to id, add playlist and HLS classes.
            // Change data-setup to match <video settings.
            if (stripos($matches[$i], '<audio') !== false) {
                preg_match('/(id_[^"]*)/i', $matches[$i], $mediaid);
                $matches[$i] = str_replace('<audio', '<video', $matches[$i]);
                $matches[$i] = preg_replace('/(id="[^"]*)/i', 'id="' . $mediaid[0] . '-video-playlist', $matches[$i]);
                $matches[$i] = str_replace('class="', 'class="video-playlist ', $matches[$i]);
                $matches[$i] = str_replace($this->audiodatasetup, $this->videodatasetup, $matches[$i]);
            }

            // Move valid sources (not iOS fallback) from <video code to playlist div/ul.
            if (stripos($matches[$i], '<source') !== false) {
                // Only process source if it is enabled media type.
                if (($this->enablevideo && preg_match('(.mp4|.flv|.f4v)', $matches[$i]) === 1)
                    || ($this->enableaudio && preg_match('(.mp3)', $matches[$i]) === 1)) {
                    // Keep count of sources so first file can be embedded in playlist player.
                    $sources++;

                    // Only process source if it is not ios source - these will be added with playlist JS.
                    if (stripos($matches[$i], '.m3u8') === false) {
                        $playlisttracks[$j] = $matches[$i];
                        $j++;
                    }
                }

                // Keep first source and related resources for intial playlist player.
                if ($sources != 1 && ($sources != 2)) {
                    // Remove subsequent sources - they will be added by video_playlist.js dynamically.
                    $matches[$i] = '';
                }
            }

            if (stripos($matches[$i], '<track') !== false) {
                // Keep count of tracks so first file can be embedded in playlist player.
                $tracks++;

                // Remove subsequent tracks - they will be added by video_playlist.js dynamically.
                if ($tracks != 1) {
                    $matches[$i] = '';
                }
            }

            // Append playlist <div with ul/li's to video code.
            if ((stripos($matches[$i], '</video>') !== false) || (stripos($matches[$i], '</audio>') !== false)) {
                if ($playlisttracks) {
                    // Use </video> instead of </audio>.
                    // Move ending </div>s after </video> closing tag.
                    $playlistcode = '</video></div>';
                    $playlistwidth = '100%';

                    // If limit size config is enabled, there will be additional wrapping <div> for width.
                    if ($this->limitsize == 1) {
                        $playlistcode .= '</div>';
                        $playlistwidth = $this->defaultwidth . 'px';
                    }

                    // Add start of <div> for playlist list.
                    // Need ID from element to concat before id=video-playlist.
                    $playlistcode .= '<div id="' . $mediaid[0]
                        . '-video-playlist-vjs-playlist" class="vjs-playlist" style="max-width:' . $playlistwidth . '"><ul>';

                    // Convert sources to li elements; include playlist name.
                    for ($m = 0; $m < count($playlisttracks); $m++) {
                        $src = array();
                        preg_match('/(rtmp:[^"]*)/i', $playlisttracks[$m], $src);
                        $titles = array();
                        preg_match('/(title=")([^"]*)/i', $playlisttracks[$m], $titles);
                        $title = $titles[2];
                        $playlistcode .= "<li><a class='vjs-track' href='#episode-{$m}' data-index='{$m}' data-src='{$src[0]}'>{$title}</a></li>";
                    }

                    // Reset $playlisttracks and $j in case another playlist in same text area.
                    $playlisttracks = array();
                    $j = 0;

                    // Add closing tags.
                    $playlistcode .= '</ul></div>';

                    // Concat after closing </video> or </audio> tag in $matches[$i + 1].
                    $matches[$i] = str_replace('</video>', $playlistcode, $matches[$i]);
                    $matches[$i] = str_replace('</audio>', $playlistcode, $matches[$i]);
                }
            }
        }

        // Concatenate code snippets.
        $playlisttext = "";
        foreach ($matches as $match) {
            $playlisttext .= $match;
        }

        return $playlisttext;
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
