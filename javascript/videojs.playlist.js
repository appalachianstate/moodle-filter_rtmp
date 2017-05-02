(function() {
    videojs.plugin('playlist', function(options) {

	var id = this.el().id;

	// Assign variables.
	var tracks = document.querySelectorAll("#" + id + "-vjs-playlist .vjs-track"),
	trackCount = tracks.length,
	player = this,
	currentTrack = tracks[0],
	index = 0,
	play = true,
	onTrackSelected = options.onTrackSelected;

	// Manually selecting track.
	for (var i = 0; i < trackCount; i++) {
	    tracks[i].onclick = function() {
		trackSelect(this);
	    }
	}

	// For continuous play.
	if (typeof options.continuous == 'undefined' || options.continuous == true) {
	    player.on("ended", function() {
		index++;
		if (index >= trackCount) {
		    index = 0;
		}
		else;
		tracks[index].click();
	    });
	}
	else;

	// Track select function for onended and manual selecting tracks.
	var trackSelect = function(track) {

	    // Get new src.
	    var src = track.getAttribute('data-src');

	    // Remove previously added captions.
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

	    // Add rtmp and ios sources, captions by MIME.
	    if (src.includes(".mp4")) {
		var ios = src.replace("rtmp", "http");
		ios = ios.replace("&mp4", "_definst_/mp4");
		ios = ios.replace(".mp4", ".mp4/playlist.m3u8");

		player.src([                
		            { type: "rtmp/mp4", src:  src },
		            { type: "video/mp4", src:  ios }
		            ]);

		var vtt = ios.replace("mp4:", "");
		vtt = vtt.replace("mp4/playlist.m3u8", "vtt");
		trackOptions.src = vtt;
		player.addRemoteTextTrack(trackOptions);
	    }
	    else if (src.includes(".flv")) {
		var ios = src.replace("rtmp", "http");
		ios = ios.replace("&flv", "_definst_/mp4");
		ios = ios.replace(".flv", ".flv/playlist.m3u8");

		player.src([                
		            { type: "rtmp/x-flv", src:  src },
		            { type: "video/mp4", src:  ios }
		            ]);

		var vtt = ios.replace("mp4:", "");
		vtt = vtt.replace("flv/playlist.m3u8", "vtt");
		trackOptions.src = vtt;
		player.addRemoteTextTrack(trackOptions);
	    }
	    else if (src.includes(".f4v")) {
		var ios = src.replace("rtmp", "http");
		ios = ios.replace("&mp4", "_definst_/mp4");
		ios = ios.replace(".f4v", ".f4v/playlist.m3u8");

		player.src([                
		            { type: "rtmp/mp4", src:  src },
		            { type: "video/mp4", src:  ios }
		            ]);

		var vtt = ios.replace("mp4:", "");
		vtt = vtt.replace("f4v/playlist.m3u8", "vtt");
		trackOptions.src = vtt;
		player.addRemoteTextTrack(trackOptions);
	    }
	    else if (src.includes(".mp3")) {
		var ios = src.replace("rtmp", "http");
		ios = ios.replace("&mp3", "_definst_/mp3");
		ios = ios.replace(".mp3", ".mp3/playlist.m3u8");

		player.src([                
		            { type: "rtmp/mp3", src:  src },
		            { type: "audio/mp3", src:  ios }
		            ]);

		var vtt = ios.replace("mp3:", "");
		vtt = vtt.replace("mp3/playlist.m3u8", "vtt");
		trackOptions.src = vtt;
		player.addRemoteTextTrack(trackOptions);
	    }

	    if (play) player.play();

	    // Remove 'currentTrack' CSS class.
	    for (var i = 0; i < trackCount; i++) {
		if (tracks[i].className.indexOf('currentTrack') !== -1) {
		    tracks[i].className = tracks[i].className.replace(/\bcurrentTrack\b/,'nonPlayingTrack');
		}
	    }

	    // Add 'currentTrack' CSS class.
	    track.className = track.className + " currentTrack";
	    if(typeof onTrackSelected === 'function') onTrackSelected.apply(track);
	}
    });
//  Return videojsplugin;
})();

//Initialize video.js.

//Get id from element and concat before video-playlist.
var playlistElements = document.getElementsByClassName("video-playlist");
var videoId = playlistElements[0].id;

videojs(videoId, {"height":"auto", "width":"400"}).ready(function(event) {
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

videojs("#audio-playlist", {"height":"auto", "width":"400","customControlsOnMobile": true}).ready(function(event) {
 var myPlayer = this;

 var playlist = myPlayer.playlist({
	'mediaType': 'audio',
	'continuous': false,
 });
 myPlayer.on('playing', function() {
	var poster = document.getElementsByClassName("vjs-poster")[1];
	poster.style.display = "block";
 });

 function resizeVideoJS() {
	var width = document.getElementById(myPlayer.el().id).parentElement.offsetWidth;
	var aspectRatio = 8 / 12;
	myPlayer.width(width).height(width * aspectRatio);
 }

 resizeVideoJS(); // Initialize the function.
 window.onresize = resizeVideoJS; // Call the function on resize.

 document.onkeydown = checkKey; // To use left and right arrows to change tracks.
 function checkKey(e) {
	e = e || window.event;
	if (e.keyCode == 37) {
	    playlist.prev();
	}
	else if(e.keyCode == 39){
	    playlist.next();
	}
 }
});