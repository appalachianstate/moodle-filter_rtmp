define(['media_videojs/video-lazy'], function(videojs) {
    return {
        initialize: function(args) {
            videojs.plugin('playlist', function(options) {

                var id = this.el().id;

                // Assign variables.
                var tracks = document.querySelectorAll("#" + id + "-vjs-playlist .vjs-track"),
                trackCount = tracks.length,
                player = this,
                play = true,
                onTrackSelected = options.onTrackSelected;

                // Manually selecting track.
                for (var i = 0; i < trackCount; i++) {
                    tracks[i].onclick = function() {
                        trackSelect(this);
                    };
                }

                // Track select function for onended and manual selecting tracks.
                var trackSelect = function(track) {

                    // Get new src.
                    var src = track.getAttribute('data-src');

                    // Closed captions by default enabled.
                    if (args.defaultcc == "1") {
                        // Remove previously dynamically added captions.
                        var captions = player.remoteTextTracks();
                        for (var i = 0; i < captions.length; i++) {
                            player.removeRemoteTextTrack(captions[i]);
                        }

                        // Set up new captions.
                        var trackOptions = new Object();
                        trackOptions.kind = 'captions';
                        trackOptions.mode = 'showing';
                        trackOptions.label = 'english';
                        trackOptions.srclang = 'en';
                        trackOptions.default = true;
                    }

                    // Add rtmp and iOS sources, captions by MIME.
                    if (src.indexOf(".mp4") != -1) {
                        // Fallback to HLS enabled.
                        if (args.hlsfallback == "1") {
                            var ios = src.replace("rtmp", args.httprotocol);
                            if (args.hlsurl.indexOf("playlist") != -1) {
                                // Wowza HLS URL format.
                                ios = ios.replace("&", "_definst_/");
                            } else {
                                // Adobe FMS HLS URL format.
                                ios = ios.replace("&", "");
                            }
                            ios = ios.replace(".mp4", ".mp4" + args.hlsurl);

                            player.src([
                                { type: "rtmp/mp4", src: src },
                                { type: "video/mp4", src: ios }
                                ]);
                        } else {
                            player.src([
                                { type: "rtmp/mp4", src: src },
                                ]);
                        }

                        // Closed captions by default enabled.
                        if (args.defaultcc == "1") {
                            var vtt = src.replace("rtmp", args.httprotocol);
                            vtt = vtt.replace(".mp4", ".vtt");
                            if (args.hlsurl.indexOf("playlist") != -1) {
                                // Wowza HLS URL format.
                                vtt = vtt.replace("&", "_definst_/");
                            } else {
                                // Adobe FMS HLS URL format.
                                vtt = vtt.replace("&", "");
                                var regex = /(\/[^\/]*\.vtt$)/i;
                                vtt = vtt.replace(regex, "/vtt$1");
                            }
                            trackOptions.src = vtt;
                            player.addRemoteTextTrack(trackOptions);
                        }
                    }
                    else if (src.indexOf(".flv") != -1) {
                        // FLV is not supported by iOS.
                        player.src([
                                    { type: "rtmp/x-flv", src: src }
                                    ]);

                        // Closed captions by default enabled.
                        if (args.defaultcc == "1") {
                            var vtt = src.replace("rtmp", args.httprotocol);
                            vtt = vtt.replace(".flv", ".vtt");
                            if (args.hlsurl.indexOf("playlist") != -1) {
                                // Wowza HLS URL format.
                                vtt = vtt.replace("&", "_definst_/");
                            } else {
                                // Adobe FMS HLS URL format.
                                vtt = vtt.replace("&", "");
                                var regex = /(\/[^\/]*\.vtt$)/i;
                                vtt = vtt.replace(regex, "/vtt$1");
                            }
                            trackOptions.src = vtt;
                            player.addRemoteTextTrack(trackOptions);
                        }
                    }
                    else if (src.indexOf(".f4v") != -1) {
                        // Fallback to HLS enabled.
                        if (args.hlsfallback == "1") {
                            var ios = src.replace("rtmp", args.httprotocol);
                            if (args.hlsurl.indexOf("playlist") != -1) {
                                // Wowza HLS URL format.
                                ios = ios.replace("&", "_definst_/");
                            } else {
                                // Adobe FMS HLS URL format.
                                ios = ios.replace("&", "");
                            }
                            ios = ios.replace(".f4v", ".f4v" + args.hlsurl);

                            player.src([
                                { type: "rtmp/mp4", src: src },
                                { type: "video/mp4", src: ios }
                                ]);
                        } else {
                            player.src([
                                { type: "rtmp/mp4", src: src },
                                ]);
                        }

                        // Closed captions by default enabled.
                        if (args.defaultcc == "1") {
                            var vtt = src.replace("rtmp", args.httprotocol);
                            vtt = vtt.replace(".f4v", ".vtt");
                            if (args.hlsurl.indexOf("playlist") != -1) {
                                // Wowza HLS URL format.
                                vtt = vtt.replace("&", "_definst_/");
                            } else {
                                // Adobe FMS HLS URL format.
                                vtt = vtt.replace("&", "");
                                var regex = /(\/[^\/]*\.vtt$)/i;
                                vtt = vtt.replace(regex, "/vtt$1");
                            }
                            trackOptions.src = vtt;
                            player.addRemoteTextTrack(trackOptions);
                        }
                    }
                    else if (src.indexOf(".mp3") != -1) {
                        // Fallback to HLS enabled.
                        if (args.hlsfallback == "1") {
                            var ios = src.replace("rtmp", args.httprotocol);
                            if (args.hlsurl.indexOf("playlist") != -1) {
                                // Wowza HLS URL format.
                                ios = ios.replace("&", "_definst_/");
                            } else {
                                // Adobe FMS HLS URL format.
                                ios = ios.replace("&", "");
                            }
                            ios = ios.replace(".mp3", ".mp3" + args.hlsurl);

                            player.src([
                                { type: "rtmp/mp4", src: src },
                                { type: "video/mp4", src: ios }
                                ]);
                        } else {
                            player.src([
                                { type: "rtmp/mp4", src: src },
                                ]);
                        }

                        // Closed captions by default enabled.
                        if (args.defaultcc == "1") {
                            var vtt = src.replace("rtmp", args.httprotocol);
                            vtt = vtt.replace(".mp3", ".vtt");
                            if (args.hlsurl.indexOf("playlist") != -1) {
                                // Wowza HLS URL format.
                                vtt = vtt.replace("&", "_definst_/");
                            } else {
                                // Adobe FMS HLS URL format.
                                vtt = vtt.replace("&", "");
                                var regex = /(\/[^\/]*\.vtt$)/i;
                                vtt = vtt.replace(regex, "/vtt$1");
                            }
                            trackOptions.src = vtt;
                            player.addRemoteTextTrack(trackOptions);
                        }
                    }

                    if (play) {
                        player.play();
                    }

                    // Remove 'currentTrack' CSS class.
                    for (var i = 0; i < trackCount; i++) {
                        if (tracks[i].className.indexOf('currentTrack') !== -1) {
                            tracks[i].className = tracks[i].className.replace(/\bcurrentTrack\b/,'nonPlayingTrack');
                        }
                    }

                    // Add 'currentTrack' CSS class.
                    track.className = track.className + " currentTrack";
                    if (typeof onTrackSelected === 'function') {
                        onTrackSelected.apply(track);
                    }
                };
            });
            // Return videojsplugin.

            // Initialize video.js.

            // Get playlist elements on page.
            var videoPlaylistElements = document.getElementsByClassName("video-playlist");

            // Get ID(s) and player(s) for all playlists on page.
            if (videoPlaylistElements.length > 0) {
                for (var i = 0; i < videoPlaylistElements.length; i++) {
                    var videoId = videoPlaylistElements[i].id;

                    videojs(videoId, {}).ready(function() {
                        var myPlayer = this;

                        myPlayer.playlist({
                            'continuous': false,
                        });

                        function resizeVideoJS() {
                            var width = document.getElementById(myPlayer.el().id).parentElement.offsetWidth;
                            var aspectRatio = 8 / 12;
                            myPlayer.width(width).height(width * aspectRatio);
                        }

                        resizeVideoJS(); // Initialize the function.
                        window.onresize = resizeVideoJS; // Call the function on resize.
                    });
                }
            }
        }
    };
});
