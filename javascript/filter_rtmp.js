// Initialize video.js.

// Get id from element and concat before video-playlist.
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