# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## 0.2.1 - 2022.08.31 Further Improvements
### Fixed
- Fixes Mapbox tiles are not moved
  [#798](https://github.com/nextcloud/maps/pull/798) @tacruc
  [#786](https://github.com/nextcloud/maps/issues/786) @Doppellhelix @BWibo
- Correct date on map tooltip-photo-date
  [#798](https://github.com/nextcloud/maps/pull/808) @tacruc
  [#786](https://github.com/nextcloud/maps/issues/807) @adripo
- Don't show Photos on ZeroIsland
  [#811](https://github.com/nextcloud/maps/pull/811) @tacruc
  [#671](https://github.com/nextcloud/maps/issues/671) @zhangguojvn @adripo
- Search for coordinates
  [#824](https://github.com/nextcloud/maps/pull/824) @tacruc
  [#802](https://github.com/nextcloud/maps/issues/802) @downtownallday



## 0.2.0 - 2022.07.18 New Vue based frontend
### Fixed
- Add .contact-group-name to padding exception
  [#678](https://github.com/nextcloud/maps/pull/678) @RobinFrcd
- Welcome PHP8
  [#683](https://github.com/nextcloud/maps/pull/683) @umgfoin fixes
  [#656](https://github.com/nextcloud/maps/issues/656) @Emporea
  [#652](https://github.com/nextcloud/maps/issues/652) @GHJester
  [#640](https://github.com/nextcloud/maps/issues/640) @benjaminsabatini
- Migrated app to Bootstrap
  [#703](https://github.com/nextcloud/maps/pull/703) @eneiluj @tacruc fixes
  [#689](https://github.com/nextcloud/maps/issues/689) @l1gi
- Improved image metadata extraction
  [#705](https://github.com/nextcloud/maps/pull/705) @tacruc
- Replace deprecated String.prototype.substr()
  [#716](https://github.com/nextcloud/maps/pull/716) @CommanderRoot
- Scan shared Photos
  [#727](https://github.com/nextcloud/maps/pull/727) @tacruc fixes
  [#447](https://github.com/nextcloud/maps/issues/447) @mayermart
- Remove empty lines from address lookup
  [708](https://github.com/nextcloud/maps/pull/708) @SeanDS fixes
  [706](https://github.com/nextcloud/maps/issues/706) @SeanDS
- Show Waypoints in tracks
  [764](https://github.com/nextcloud/maps/pull/764) @tacruc fixes
  [753](https://github.com/nextcloud/maps/issues/753) @nougatbyte
- Allways show tracks without timestamps
  [739](https://github.com/nextcloud/maps/pull/739) @tacruc fixes
  [738](https://github.com/nextcloud/maps/issues/738) @nougatbyte
### Added
- new Vue frontend
  [#510](https://github.com/nextcloud/maps/pull/510) @eneiluj @tacruc
  [#700](https://github.com/nextcloud/maps/pull/700) @tacruc
- Added Option to scan photos now3
  [#704](https://github.com/nextcloud/maps/pull/704) @tacruc
- Added phpUnit tests workflow
  [#725](https://github.com/nextcloud/maps/pull/725) @tacruc
- Added unit test for exif extraction
  [737](https://github.com/nextcloud/maps/pull/737) @tacruc
- Added node  workflow
  [740](https://github.com/nextcloud/maps/pull/740) @tacruc
- Respect .nomedia and .noimage files
  [7226](https://github.com/nextcloud/maps/pull/726) @tacruc
-

### Performance
- Replace use of IConfig with IMemcache to store the last address lookup
  [#688](https://github.com/nextcloud/maps/pull/688) @tcitworld

## 0.1.10 – 2021-12-20
### Fixed
- Add padding on icons/name
  [#654](https://github.com/nextcloud/maps/pull/654) @RobinFrcd

## 0.1.9 – 2021-06-29
### Added
- GitHub automated release action

### Fixed
- fix tracks not loading
  [#587](https://github.com/nextcloud/maps/pull/587) @Ablu
  [#574](https://github.com/nextcloud/maps/issues/574) @Tazzios
- fix images not loading
  [#559](https://github.com/nextcloud/maps/pull/559) @tacruc
  [#543](https://github.com/nextcloud/maps/issues/543) @beardhatcode
- fix db-related install problems on NC 21
  [#568](https://github.com/nextcloud/maps/pull/568) @eneiluj
  [#541](https://github.com/nextcloud/maps/issues/541) @J0WI

## 0.1.8 – 2020-10-03
### Fixed
- controllers not being declared soon enough in some cases
- OC.disallowNavigationBarSlideGesture being absent in some cases

## 0.1.7 – 2020-10-01
### Added
- favorite sharing by public link
[#217](https://github.com/nextcloud/maps/pull/217) @paulschwoerer
- import favorites from Google Maps/Takeout GeoJSON files
[#338](https://github.com/nextcloud/maps/pull/338) @simonspa
- handle coordinate search
[#420](https://github.com/nextcloud/maps/pull/420) @tacruc @eneiluj

### Changed
- use NC Viewer and show right sidebar for displayed photo file (with Sharing and Talk tabs)
[#418](https://github.com/nextcloud/maps/pull/418) @tacruc
- adapt to NC 20

### Fixed
- image count when > 1000
[#378](https://github.com/nextcloud/maps/pull/378) @wronny
- catch PEL warnings
[#369](https://github.com/nextcloud/maps/pull/369) @tacruc
- mistake in Migrations preventing app installation in some cases
[#453](https://github.com/nextcloud/maps/pull/453) @eneiluj
- correctly load inital category states
[#462](https://github.com/nextcloud/maps/pull/462) @eneiluj
- handle storage exceptions on occ scan import
[#436](https://github.com/nextcloud/maps/pull/436) @vwbusguy

## 0.1.6 – 2020-03-09
### Added
- dialog to choose wether to place photo files or directories
[#290](https://github.com/nextcloud/maps/pull/290) @wronny

### Changed
- a few style improvements in tooltips (position and size)
- message when adding a favorite, Esc to cancel

### Fixed
- missing variable declarations breaking things in search, favorites, tracks, devices
[#305](https://github.com/nextcloud/maps/pull/305) @doc75
[#308](https://github.com/nextcloud/maps/pull/308) @Bergum
- delay when placing photos
[#290](https://github.com/nextcloud/maps/pull/290) @wronny
- map left click activation in lots of cases
- favorite and contact group style bug
- cursor style with mapboxgl map
- set max zoom level on tracks
- bug when updating positions of disabled devices
- L.Elevation mouse events

## 0.1.2 – 2019-09-01
### Added
- Mapbox profiles (car, foot, bike)
[#103](https://github.com/nextcloud/maps/pull/103) @tacruc

### Fixed
- catch PelExceptions
[#91](https://github.com/nextcloud/maps/pull/91) @tacruc
[#96](https://github.com/nextcloud/maps/issues/96) @FrouxBY
[#97](https://github.com/nextcloud/maps/issues/97) @matiasdelellis
- bug with chrome based mobile browsers
[#100](https://github.com/nextcloud/maps/pull/100) @eneiluj
[#99](https://github.com/nextcloud/maps/issues/99) @szaimen
- fix bad inserted values for photo lat/lng
[#95](https://github.com/nextcloud/maps/issues/95) @tacruc
[#98](https://github.com/nextcloud/maps/pull/98) @eneiluj @tacruc
- bug when getting vCard address, get rid of post office box vCard address field
[#94](https://github.com/nextcloud/maps/pull/94) @eneiluj

## 0.1.1 – 2019-08-29
### Fixed
- bug with unnamed contacts
[#87](https://github.com/nextcloud/maps/pull/87) @eneiluj
[#85](https://github.com/nextcloud/maps/issues/85) @tacruc

## 0.1.0 – 2019-08-29
### Added
- First release!

## 0.0.1 – 2019-07-01
### Added
- pretty much everything
