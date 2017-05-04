define(['media_videojs/video-lazy'], function(videojs) {
    videojs.plugin('playlist', function(options) {

        var id = this.el().id;

        // Get HLS class for formatting HLS URLs.
        var classes = document.getElementById(id).className;
        var hlsurl;
        if (classes.includes("fms")) {
            hlsurl = ".m38u";
        } else {
            hlsurl = "/playlist.m3u8";
        }

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

            // Add rtmp and iOS sources, captions by MIME.
            if (src.includes(".mp4")) {
                var ios = src.replace("rtmp", "http");
                ios = ios.replace("&mp4", "_definst_/mp4");
                ios = ios.replace(".mp4", ".mp4" + hlsurl);

                player.src([
                            { type: "rtmp/mp4", src: src },
                            { type: "video/mp4", src: ios }
                            ]);

                var vtt = ios.replace("mp4:", "");
                vtt = vtt.replace("mp4" + hlsurl, "vtt");
                trackOptions.src = vtt;
                player.addRemoteTextTrack(trackOptions);
            }
            else if (src.includes(".flv")) {
                var ios = src.replace("rtmp", "http");
                ios = ios.replace("&flv", "_definst_/mp4");
                ios = ios.replace(".flv", ".flv" + hlsurl);

                player.src([
                            { type: "rtmp/x-flv", src: src },
                            { type: "video/mp4", src: ios }
                            ]);

                var vtt = ios.replace("mp4:", "");
                vtt = vtt.replace("flv" + hlsurl, "vtt");
                trackOptions.src = vtt;
                player.addRemoteTextTrack(trackOptions);
            }
            else if (src.includes(".f4v")) {
                var ios = src.replace("rtmp", "http");
                ios = ios.replace("&mp4", "_definst_/mp4");
                ios = ios.replace(".f4v", ".f4v" + hlsurl);

                player.src([
                            { type: "rtmp/mp4", src: src },
                            { type: "video/mp4", src: ios }
                            ]);

                var vtt = ios.replace("mp4:", "");
                vtt = vtt.replace("f4v" + hlsurl, "vtt");
                trackOptions.src = vtt;
                player.addRemoteTextTrack(trackOptions);
            }
            else if (src.includes(".mp3")) {
                var ios = src.replace("rtmp", "http");
                ios = ios.replace("&mp3", "_definst_/mp3");
                ios = ios.replace(".mp3", ".mp3" + hlsurl);

                player.src([
                            { type: "rtmp/mp3", src: src },
                            { type: "audio/mp3", src: ios }
                            ]);

                var vtt = ios.replace("mp3:", "");
                trackOptions.src = vtt;
                player.addRemoteTextTrack(trackOptions);
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
});
