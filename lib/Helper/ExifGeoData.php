<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gergely Kovács 2021
 * @copyright Gergely Kovács 2021
 */

namespace OCA\Maps\Helper;

use lsolesen\pel\Pel;
use lsolesen\pel\PelDataWindow;
use lsolesen\pel\PelEntryTime;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelInvalidArgumentException;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelTiff;

//PHP 7 polyfill
if (!function_exists('str_contains')) {
	function str_contains(string $haystack, string $needle): bool {
		return $needle === '' || strpos($haystack, $needle) !== false;
	}
}

/**
 * Class ExifGeoData
 *
 * @property-read ?float $lat
 * @property-read ?float $lng
 * @property-read ?int $dateTaken
 */
class ExifGeoData extends \stdClass implements \JsonSerializable {
	/**
	 * Mime type regex
	 */
	private const SUPPORTED_MIMETYPES = 'image\/(jpe?g|tiff)';

	/**
	 * Exif Latitude attribute names
	 */
	private const LATITUDE_REF = 'GPSLatitudeRef';
	private const LATITUDE = 'GPSLatitude';

	/**
	 * Exif Longitude attribute names
	 */
	private const LONGITUDE_REF = 'GPSLongitudeRef';
	private const LONGITUDE = 'GPSLongitude';

	/**
	 * Exif Timestamp attribute name
	 */
	private const TIMESTAMP = 'DateTimeOriginal';

	/**
	 * Coordinate modulo
	 * Modulo between degree, minute, second
	 */
	private const COORDINATE_MODULO = 60;


	/**
	 * Regex to extract date components from exif parameter
	 */
	private const EXIF_TIME_REGEX = '(?<years>[0-9]{4})\:(?<months>[0-9]{2})\:(?<days>[0-9]{2}) (?<hours>[0-9]{2})\:(?<minutes>[0-9]{2})\:(?<seconds>[0-9]{2})';

	/**
	 * @var int|null
	 */
	private $timestamp = null;

	/**
	 * @var float|null
	 */
	private $latitude = null;

	/**
	 * @var float|null
	 */
	private $longitude = null;

	/**
	 * @var bool|null
	 */
	private $is_valid = null;

	/**
	 * @var ?array
	 */
	protected $exif_data = null;

	/**
	 * @throws PelInvalidArgumentException
	 */
	protected static function get_exif_data_array(string $path) : array {
		if (function_exists('exif_read_data')) {
			$data = @exif_read_data($path, null, true);
			if ($data && isset($data['EXIF']) && is_array($data['EXIF']) && isset($data['EXIF'][self::LATITUDE]) && isset($data['EXIF'][self::LONGITUDE])) {
				return $data['EXIF'];
			} elseif ($data && isset($data['GPS']) && is_array($data['GPS']) && isset($data['GPS'][self::LATITUDE]) && isset($data['GPS'][self::LONGITUDE])) {
				$d = $data['GPS'];
				if (!isset($d[self::TIMESTAMP]) && isset($data['EXIF'][self::TIMESTAMP])) {
					$d[self::TIMESTAMP] = $data['EXIF'][self::TIMESTAMP];
				}
				return $d;
			}
		}
		$data = new PelDataWindow(file_get_contents($path));
		if (PelJpeg::isValid($data)) {
			$pelJpeg = new PelJpeg($data);

			$pelExif = $pelJpeg->getExif();
			if ($pelExif === null) {
				return [];
			}

			$pelTiff = $pelExif->getTiff();
		} elseif (PelTiff::isValid($data)) {
			$pelTiff = new PelTiff($data);
		} else {
			return [];
		}
		if (is_null($pelTiff)) {
			return [];
		}
		$pelIfd0 = $pelTiff->getIfd();
		if (is_null($pelIfd0)) {
			return [];
		}
		$pelIfdExif = $pelIfd0->getSubIfd(PelIfd::EXIF);

		if (is_null($pelIfdExif)) {
			return [];
		}
		$pelDateTimeOriginal = $pelIfdExif->getEntry(PelTag::DATE_TIME_ORIGINAL);
		if (is_null($pelDateTimeOriginal)) {
			return [];
		}
		$exif = [
			# self::TIMESTAMP => $pelDateTimeOriginal->getValue(PelEntryTime::EXIF_STRING) // for old pel 0.9.6 and above
			self::TIMESTAMP => (int)$pelDateTimeOriginal->getValue() // for new pel >= 0.9.11
		];
		$pelIfdGPS = $pelIfd0->getSubIfd(PelIfd::GPS);
		if (!is_null($pelIfdGPS) && !is_null($pelIfdGPS->getEntry(PelTag::GPS_LATITUDE)) && !is_null($pelIfdGPS->getEntry(PelTag::GPS_LONGITUDE))) {
			static::readPelCoordinate($pelIfdGPS,
				self::LATITUDE, PelTag::GPS_LATITUDE,
				self::LATITUDE_REF, PelTag::GPS_LATITUDE_REF,
				$exif
			);
			static::readPelCoordinate($pelIfdGPS,
				self::LONGITUDE, PelTag::GPS_LONGITUDE,
				self::LONGITUDE_REF, PelTag::GPS_LONGITUDE_REF,
				$exif
			);
		}
		Pel::clearExceptions();
		return $exif;
	}

	/**
	 * @param PelIfd $pelIfdGPS
	 * @param $target
	 * @param $source
	 * @param $ref_target
	 * @param $ref_source
	 * @param array $exif
	 */
	protected static function readPelCoordinate(PelIfd $pelIfdGPS, $target, $source, $ref_target, $ref_source, array &$exif = []) : void {
		$coordinate = $pelIfdGPS->getEntry($source)->getValue();
		if ((int)$coordinate[0][1] != 0 && (int)$coordinate[1][1] != 0 && (int)$coordinate[2][1] != 0) {
			$exif[$ref_target] = $pelIfdGPS->getEntry($ref_source)->getValue();
			$exif[$target] = [
				0 => (int)$coordinate[0][0] / (int)$coordinate[0][1],
				1 => (int)$coordinate[1][0] / (int)$coordinate[1][1],
				2 => (int)$coordinate[2][0] / (int)$coordinate[2][1]
			];
		}
	}

	/**
	 * @param string $path
	 * @return ExifGeoData
	 */
	public static function get(string $path) : ?ExifGeoData {
		try {
			$data = static::get_exif_data_array($path);
		} catch (\Throwable $e) {
			$data = [];
		}
		return new static($data);
	}

	/**
	 * ExifGeoData constructor.
	 * @param array $exif_data
	 */
	final private function __construct(array $exif_data) {
		$this->exif_data = $exif_data;
		$this->parse();
	}

	/**
	 * @param bool $invalidate_zero_iland
	 * @throws ExifDataInvalidException
	 * @throws ExifDataNoLocationException
	 */
	public function validate($invalidate_zero_iland = false) {
		if (!$this->exif_data) {
			throw new ExifDataInvalidException('No exif_data found', 1);
		}
		if (!is_array($this->exif_data)) {
			throw new ExifDataInvalidException('exif_data is not an array', 2);
		}

		if (!isset($this->exif_data[self::LATITUDE]) || !isset($this->exif_data[self::LONGITUDE])) {
			throw new ExifDataNoLocationException('Latitude and/or Longitude are missing from exif data', 1);
		}
		if ($invalidate_zero_iland && $this->isZeroIsland()) {
			$this->latitude = null;
			$this->longitude = null;
			throw new ExifDataNoLocationException('Zero island is not valid', 2);
		}
	}

	/**
	 * @return bool
	 */
	public function isValid(): bool {
		if ($this->is_valid === null) {
			try {
				$this->validate();
				$this->is_valid = true;
			} catch (\Throwable  $e) {
				$this->is_valid = false;
			}
		}
		return $this->is_valid;
	}

	private function parse(): void {
		if ($this->isValid() && ($this->latitude === null || $this->longitude === null)) {
			$this->longitude = $this->geo2float($this->exif_data[self::LONGITUDE]);
			if (isset($this->exif_data[self::LONGITUDE_REF]) && $this->exif_data[self::LONGITUDE_REF] === 'W') {
				$this->longitude *= -1;
			}
			$this->latitude = $this->geo2float($this->exif_data[self::LATITUDE]);
			if (isset($this->exif_data[self::LATITUDE_REF]) && $this->exif_data[self::LATITUDE_REF] === 'S') {
				$this->latitude *= -1;
			}
		}
		// optional
		if (isset($this->exif_data[self::TIMESTAMP])) {
			$t = $this->exif_data[self::TIMESTAMP];
			$this->timestamp = is_string($t) ? $this->string2time($t) : (is_int($t) ? $t : null);
		}
	}

	/**
	 * @param string $timestamp
	 * @return int
	 */
	private function string2time(string $timestamp): ?int {
		$result = null;
		if (preg_match('#' . self::EXIF_TIME_REGEX . '#ui', $timestamp, $match)) {
			$result =
				strtotime("{$match['years']}-{$match['months']}-{$match['days']} {$match['hours']}:{$match['minutes']}:{$match['seconds']}");
		}
		return $result;
	}


	/**
	 * @param $geo
	 * @return float|null
	 */
	private function geo2float($geo): ?float {
		if (!is_array($geo)) {
			$geo = [$geo];
		}
		$result = .0;
		$d = 1.0;
		foreach ($geo as $component) {
			$result += ($this->string2float($component) * $d);
			$d /= self::COORDINATE_MODULO;
		}

		return $result;
	}

	/**
	 * @param string $value
	 * @return float|null
	 */
	private function string2float(string $value): ?float {
		$result = null;
		$value = trim($value, '/');
		if (str_contains($value, '/')) {
			$value = array_map('intval', explode('/', $value));
			if ($value[1] != 0) {
				$result = $value[0] / $value[1];
			}
		} else {
			$result = floatval($value);
		}
		return $result;

	}

	/**
	 * @return bool
	 */
	public function isZeroIsland(): bool {
		return $this->latitude() === .0 && $this->longitude() === .0;
	}

	/**
	 * @return float
	 */
	public function latitude(int $precision = 6): ?float {
		return $this->latitude === null ? null : round($this->latitude, $precision);
	}

	/**
	 * @return float
	 */
	public function longitude(int $precision = 6): ?float {
		return $this->longitude === null ? null : round($this->longitude, $precision);
	}

	/**
	 * @param string|null $format
	 * @return string|int|null
	 */
	public function timestamp(?string $format = 'Y-m-d H:i:s') {
		$result = $this->timestamp;
		if ($this->timestamp !== null && $format) {
			$result = date($format, $this->timestamp);
		}
		return $result;
	}

	/**
	 * If someone wants to have it as a json object
	 */
	public function jsonSerialize(): array {
		return [
			'lat' => $this->lat,
			'lng' => $this->lng,
			'dateTaken' => $this->dateTaken
		];
	}

	/**
	 * Magic getter function
	 *
	 * @param $name
	 * @return float|int|string|null
	 */
	public function __get($name) {
		$value = null;
		switch ($name) {
			case 'lat':
				$value = $this->latitude();
				break;
			case 'lng':
				$value = $this->longitude();
				break;
			case 'dateTaken':
				$value = $this->timestamp(null);
				break;
		}
		return $value;
	}

}
