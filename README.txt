 README for filter_rtmp plugin/module
 
 DISCLAIMER AND LICENSING
 ------------------------
 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or (at
 your option) any later version.
 
 This program is distributed in the hope that it will be useful, but
 WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 General Public License for more details.
 
 You should have received a copy of the GNU General Public License
 along with this program. If not, see <http://www.gnu.org/licenses/>.
 

 GENERAL INFORMATION
 -------------------
 The RTMP filter looks for anchor href values that begin with
 'rtmp://' and HTML5 video or audio code that contains RTMP 
 src and substitutes the necessary HTML to work with Moodle's
 media plugin, VideoJS.
 
 The videojs-playlist plugin code was modified from
 https://github.com/tim-peterson/videojs-playlist.
 

 INSTALLATION
 ------------
 Place the rtmp directory in the site's filter directory. Access the
 notifications admin page to confirm installation. Select which of the
 the players (audio or video) you want enabled for filtering.
 
 
 SETTINGS
 --------
 Filter audio (.mp3): .mp3 files inserted as links, audio, or video will be rendered in a VideoJS player.
 Filter video (.flv|.mp4|.f4v): .flv, .mp4, and .f4v files inserted as links, audio, or video will be rendered in a VideoJS player.
 Closed captions on by default: If enabled, the filter will automatically add track files for captions.
 * Captions must be web video text track (WebVTT) files.
 * WebVTT captions must be enabled on the streaming server application.
 * For Wowza, caption files must have same name and location as corresponding media file.
 * For Adobe FMS, caption files must have same name and location, in a 'vtt' subdirectory, as corresponding media file.
 Fallback to HLS: If enabled, the filter will automatically add HLS sources.
 HLS/Captions URL style: Select the streaming server media engine for corresponding HLS and caption URL formatting.
 * Wowza: .mp4/playlist.m3u8
 * Adobe FMS: .mp4.m3u8
 HTTPS: If enabled, the filter will automatically use https protocol for HLS and caption URLs. 
 Note that if Moodle uses HTTPS, the streaming server will also need to use HTTPS, so HLS and caption URLs will not be blocked.
 CORS: The filter will automatically set the cross-origin resource sharing attribute to anonymous. 
 Note that if Moodle is on a different domain than the streaming server, CORS will need to be enabled on the streaming server application.
 Use Flash fallback: Enable in VideoJS player settings for .flv files to work.
 Compatibility
 * .mp3 file inserted as video with captions will not play on iOS.
 * .flv file will not play on iOS.
 

 APACHE LICENSE, VERSION 2.0
 ---------------------------
 Copyright 2013 Brightcove, Inc.

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
