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
 

 INSTALLATION
 ------------
 Place the rtmp directory in the site's filter directory. Access the
 notifications admin page to confirm installation. Select which of the
 the players (audio or video) you want enabled for filtering.
