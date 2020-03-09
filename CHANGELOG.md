# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
## 0.1.6 – 2020-03-09
### Added
- dialog to choose wether to place photo files or directories
[#290](https://github.com/nextcloud/maps/pull/290) @wronny

### Changed
- a few style improvements in tooltips (position and size)
- message when adding a favorite, ESC to cancel

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
