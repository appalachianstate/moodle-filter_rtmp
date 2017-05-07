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

        // Handle all links that contain any 'embeddable' marker text.
        // (It could do all links, but the embeddable markers thing should make
        // it faster by meaning for most links it doesn't drop into PHP code).
        if (stripos($text, '</a>')) {
            // Get embed markers from RTMP filter settings.
            $embedmarkers = "";

            $enableaudio = $CFG->filter_rtmp_enable_audio;
            if (!is_numeric($enableaudio) || $enableaudio <= 0) {
                $enableaudio = '0';
            }
            if ($enableaudio) {
                $embedmarkers .= "\.mp3";
            }

            $enablevideo = $CFG->filter_rtmp_enable_video;
            if (!is_numeric($enablevideo) || $enablevideo <= 0) {
                $enablevideo = '0';
            }
            if ($enablevideo) {
                if (!$embedmarkers == "") {
                    $embedmarkers .= "|";
                }
                $embedmarkers .= "\.flv|\.mp4|\.f4v";
            }

            // Regex gets string from starting <a tag to closing </a> tag for rtmp single video and playlists links.
            $regex = '~<a\s[^>]*href="(rtmp:\/\/(?:playlist=[^"]*|[^"]*(?:' . $embedmarkers . '))[^"]*)"[^>]*>([^>]*)</a>~is';
            $newtext = preg_replace_callback($regex, array($this, 'callback'), $text);

            if ($newtext == $text || is_null($text)) {
                return $text;
            }

            // Set changed text to text, so filter can continue work
            // on modifiled <a tags or already set <video or <audio tags.
            $text = $newtext;
        }

        $matches = preg_split('/(<video[^>]*>)|(<audio[^>]*>)|(<source[^>]*>)/i', $text, -1,
                PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if (!$matches) {
            return $text;
        }

        // Get and verify default width and height settings.
        $width = $CFG->media_default_width;
        $height = $CFG->media_default_height;
        if (!is_numeric($width) || $width <= 0) {
            $width = '400';
        }
        if (!is_numeric($height) || $height <= 0) {
            $height = '300';
        }

        // Set VideoJS video data-setup values.
        // Remove "fluid": true (not compatible with RTMP).
        // Add width setting.
        // Add height setting.
        // Add "techOrder": "flash", "html5" (set priority for Flash and HTML5 playback; required for RTMP).
        $this->videodatasetup = 'data-setup="{&quot;language&quot;: &quot;en&quot;, &quot;width&quot;: '
                . $width . ', &quot;height&quot;: ' . $height
                . ', &quot;techOrder&quot;: [&quot;flash&quot;, &quot;html5&quot;]}"';

        // Set VideoJS audio data-setup values.
        // Add width setting.
        // Add "techOrder": "flash", "html5" (set priority for Flash and HTML5 playback; required for RTMP).
        $this->audiodatasetup = 'data-setup="{&quot;language&quot;: &quot;en&quot;, &quot;fluid&quot;: true,'
                . '&quot;controlBar&quot;: {&quot;fullscreenToggle&quot;: false}, &quot;aspectRatio&quot;: &quot;1:0&quot;,'
                . '&quot;width&quot;: ' . $width . ', &quot;techOrder&quot;: [&quot;flash&quot;, &quot;html5&quot;]}"';

        // Get and verify HLS fallback config.
        $hlsfallback = $CFG->filter_rtmp_hls_fallback;
        if (!is_numeric($hlsfallback) || $hlsfallback <= 0) {
            $hlsfallback = '0';
        }

        // Get and verify CC config.
        $defaultcc = $CFG->filter_rtmp_default_cc;
        if (!is_numeric($defaultcc) || $defaultcc <= 0) {
            $defaultcc = '0';
        }

        // Format <video, <audio and <source tags.
        for ($i = 0; $i < count($matches); $i++) {
            if (stripos($matches[$i], '<video') !== false) {
                // Add crossorigin config. Adjust data-setup config.
                $replacement = 'crossorigin="anonymous" ' . $this->videodatasetup;
                $matches[$i] = preg_replace('/(data-setup="[^"]*")/i', $replacement, $matches[$i]);
            }

            if (stripos($matches[$i], '<audio') !== false) {
                // Add crossorigin config. Adjust data-setup config.
                $replacement = 'crossorigin="anonymous" ' . $this->audiodatasetup;
                $matches[$i] = preg_replace('/(data-setup="[^"]*")/i', $replacement, $matches[$i]);
            }

            if (stripos($matches[$i], '<source') !== false) {
                // Format RTMP URL for VideoJS - add & and MIME type.
                $matches[$i] = self::format_url($matches[$i]);

                // If HLS fallback is set, add iOS source.
                if ($hlsfallback) {
                    $hlssource = self::get_hls_source($matches[$i]);
                    $matches[$i] .= $hlssource;
                }

                // If closed captions on by default is set and parent is video tag, add track code for captions.
                if (stripos($matches[$i - 1], '<video') !== false && $defaultcc) {
                    // Use HLS source as base for track code filtering.
                    if ($hlssource == '') {
                        $hlssource = self::get_hls_source($matches[$i]);
                    }
                    $trackcode = self::get_captions($hlssource);
                    $matches[$i] .= $trackcode;
                }
            }
        }

        // Concatenate <video, <audio and <source snippets.
        $filteredtext = "";
        foreach ($matches as $match) {
            $filteredtext .= $match;
        }

        // If filtered text has any playlist content, prepare to format it.
        if (stripos($filteredtext, 'class="video-js playlist') !== false) {
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
                if (stripos($matches[$i], 'class="video-js playlist') !== false) {
                    $matches[$i] = self::format_for_playlist($matches[$i], $width);

                    // Remove ending </div>s from $matches[$i + 1] - moved to $matches[$i] in playlist formatting.
                    $matches[$i + 1] = str_replace('</div></div>', '', $matches[$i + 1]);
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
            $result = str_replace('class="video-js', 'class="video-js playlist', $result);
        }

        // Get playlist names (if provided), or filenames (if not).
        if (stripos($result, 'class="video-js playlist') !== false) {
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

                        // Add a title attribute to the source tag with the name, so it can be displayed later.
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
     * Format URL for VideoJS RTMP.
     * Add & and MIME type before username.
     * Change type to rtmp.
     * Remove '+' for spaces if needed.
     *
     * @access private
     * @static
     *
     * @param string $source
     * @return string RTMP formatted URL for VideoJS
     */
    private static function format_url($source) {
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

        // Replace '+' in file path with space for VideoJS compatibility.
        if (stripos($source, '+') !== false) {
            $source = str_replace('+', ' ', $source);
        }
        return $source;
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

        // Use RTMP formatted source to update for HLS.
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

        // Create track code with corresponding src URL, add to end of sources.
        $trackcode = '<track kind="captions" src="' . $captionfile . '" srclang="en" label="English" default="">';
        return $trackcode;
    }

    /**
     * Format code for playlist for VideoJS.
     *
     * @access private
     * @static
     *
     * @param   string    $filteredtext
     * @return  string    playlist formatted code
     *
     * @uses $PAGE
     */
    private function format_for_playlist($filteredtext, $width) {
        global $PAGE;
        $PAGE->requires->js_call_amd('filter_rtmp/videojs_playlist', 'videojs.plugin');

        // Split text into <video, <audio, <source, <track and </video> snippets.
        $matches = preg_split('/(<video[^>]*>)|(<audio[^>]*>)|(<source[^>]*>)|(<track[^>]*>)|(<\/video[^>]*>)/i',
                $filteredtext, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if (!$matches) {
            return $filteredtext;
        }

        $playlisttracks = array();
        $mediaid = array();

        // Prepare to set <video class to include HLS URL type for playlist javascript to use.
        if (in_array('.m38u', $matches)) {
            $hlsclass = 'fms';
        } else {
            $hlsclass = 'wse';
        }

        for ($i = 0, $j = 0; $i < count($matches); $i++) {
            // Format video tag to append '-video-playlist' to id, add playlist and HLS classes.
            if (stripos($matches[$i], '<video') !== false) {
                preg_match('/(id_[^"]*)/i', $matches[$i], $mediaid);
                $matches[$i] = preg_replace('/(id="[^"]*)/i', 'id="' . $mediaid[0] . '-video-playlist', $matches[$i]);
                $matches[$i] = str_replace('class="', 'class="video-playlist vjs-default-skin ' . $hlsclass . ' ', $matches[$i]);
            }

            // Format <audio tag to <video for playlists (works better for playlist).
            // Append '-video-playlist' to id, add playlist and HLS classes.
            // Change data-setup to match <video settings.
            if (stripos($matches[$i], '<audio') !== false) {
                preg_match('/(id_[^"]*)/i', $matches[$i], $mediaid);
                $matches[$i] = str_replace('<audio', '<video', $matches[$i]);
                $matches[$i] = preg_replace('/(id="[^"]*)/i', 'id="' . $mediaid[0] . '-video-playlist', $matches[$i]);
                $matches[$i] = str_replace('class="', 'class="video-playlist vjs-default-skin ' . $hlsclass . ' ', $matches[$i]);
                $matches[$i] = str_replace($this->audiodatasetup, $this->videodatasetup, $matches[$i]);
            }

            // Move valid sources (not iOS fallback) from video/audio tag to playlist div/ul.
            if (stripos($matches[$i], '<source') !== false) {
                if (stripos($matches[$i], 'playlist.m3u8') === false && stripos($matches[$i], '.m38u') === false) {
                    if (stripos($matches[$i], '&') === false) {
                        // Only the first source will be formatted for VideoJS RTMP.
                        // Reformat subsequent source URL for RTMP.
                        $matches[$i] = self::format_url($matches[$i]);
                    }
                    $playlisttracks[$j] = $matches[$i];
                    $j++;
                }
                $matches[$i] = '';
            }

            // Remove track code - will be added by video_playlist.js dynamically.
            if (stripos($matches[$i], '<track') !== false) {
                $matches[$i] = '';
            }

            // Append playlist <div with ul/li's to video code.
            if (stripos($matches[$i], '</video>') !== false || stripos($matches[$i], '</audio>') !== false) {
                if ($playlisttracks) {
                    // Use </video> instead of </audio>.
                    // Move ending </div>s after </video> closing tag.
                    // Add start of <div> for playlist list.
                    // Need ID from element to concat before id=video-playlist.
                    $playlistcode = '</video></div></div><div id="'
                            . $mediaid[0] . '-video-playlist-vjs-playlist" class="vjs-playlist" style="width:'
                            . $width . 'px"><ul>';

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
