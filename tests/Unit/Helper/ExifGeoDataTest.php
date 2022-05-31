<?php

namespace OCA\Maps\Helper;

use PHPUnit\Framework\TestCase;
use OCA\Maps\Helper\ExifGeoData;

class ExifGeoDataTest extends TestCase {

	public function imageWithDateAndLocationProvider(): array {
		return [
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation1.JPG", 1311984000 + 7200, 47.071717, 10.339557],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation2.JPG", 1312156800 + 7200, 46.862350, 10.916452],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation3.JPG", 1312070400 + 7200, 47.069058, 10.329370],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation4.JPG", 1312070400 + 7200, 47.059160, 10.312354],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation5.JPG", 1568101093 + 7200, 47.357735, 11.177585],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation6.JPG", 1577630208 + 3600, 50.083045, 9.986018],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation7.jpg", 1568999599 + 7200, 49.420833, 11.114444],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation8.JPG", 1501431401 + 7200, 45.306983, 10.700902],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation9.JPG", 1302998400 + 7200, 52.363055, 4.903418],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation10.JPG", 1501238375 + 7200, 46.388742, 11.266598],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation11.JPG", 1501567361 + 7200, 44.827830, 10.956387],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation12.JPG", 1501591333 + 7200, 44.528283, 11.262207],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation13.jpg", 1640083235 + 3600, 54.359561, 10.017325],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation14.jpg", 1559327910 + 7200, 52.976844, 12.988281],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation15.jpg", 1559332394 + 7200, 52.983697, 12.935217],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation16.jpeg", 1593458542 + 7200, 62.733947, 6.779617],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation17.jpeg", 1593458620 + 7200, 62.733769, 6.777794],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation18.jpeg", 1596136867 + 7200, 54.350891, 9.903506],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation19.jpeg", 1596136833 + 7200, 54.350894, 9.903505],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation20.jpeg", 1592913150 + 7200, 61.351753, 6.519107],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation21.jpg", 1653565075 + 7200, 48.704331, 8.418475],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation22.jpeg", 1593890841 + 7200, 62.735419, 7.155311],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation23.jpeg", 1592904886 + 7200, 61.217086, 6.558886],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation24.jpeg", 1592677991 + 7200, 60.427481, 6.548446],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation25.jpeg", 1592650395 + 7200, 59.860523, 6.696346],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation26.jpeg", 1592770386 + 7200, 60.594022, 6.581317],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation27.jpeg", 1592654095 + 7200, 60.033561, 6.563068],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation28.jpg", 1595326357 + 7200, 59.852992, 6.714458],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation29.jpg", 1594918175 + 7200, 57.595925, 9.976864],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation30.jpg", 1595418724 + 7200, 60.669492, 6.807386],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation31.jpg", 1594934141 + 7200, 57.801164, 8.314269],
			["tests/test_files/Photos/WithDateAndLocation/imageWithDateAndLocation32.jpeg", 1595629060 + 7200, 59.598981, 9.677297],
		];
	}
	/**
	 * @dataProvider imageWithDateAndLocationProvider
	 */
	public function testImagesWithDateAndLocation(string $path, int $date, float $lat, float $lng) {
		$exif_geo_data = ExifGeoData::get($path);
		$exif_geo_data->validate(true);
		$this->assertEquals($date, $exif_geo_data->dateTaken);
		//This is the same upto ~55cm
		$this->assertEqualsWithDelta($lat, $exif_geo_data->lat,  	0.000005);
		$this->assertEqualsWithDelta($lng, $exif_geo_data->lng, 0.000005);
	}
}
