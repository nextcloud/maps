<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Maps\Service;

use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\Share\IManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TracksServiceTest extends TestCase {
	/** Length of 0.001° of latitude along a meridian, with the earth radius used by distance() */
	private const MILLIDEGREE = 6371000 * M_PI / 180 / 1000;

	private TracksService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new TracksService(
			$this->createMock(LoggerInterface::class),
			$this->createMock(IRootFolder::class),
			$this->createMock(IManager::class),
			$this->createMock(IDBConnection::class),
		);
	}

	public function testMovingStatsOnlyUseTheMovingDistance(): void {
		// two 60 s moving legs, with a slow 30 min drift in between that counts as stopped
		$metadata = $this->generateMetadata('<trk><trkseg>'
			. '<trkpt lat="45.0000" lon="10"><time>2026-01-01T10:00:00Z</time></trkpt>'
			. '<trkpt lat="45.0010" lon="10"><time>2026-01-01T10:01:00Z</time></trkpt>'
			. '<trkpt lat="45.0011" lon="10"><time>2026-01-01T10:31:00Z</time></trkpt>'
			. '<trkpt lat="45.0021" lon="10"><time>2026-01-01T10:32:00Z</time></trkpt>'
			. '</trkseg></trk>');

		$movingDistance = 2 * self::MILLIDEGREE;
		self::assertEqualsWithDelta(2.1 * self::MILLIDEGREE, $metadata['distance'], 0.01);
		self::assertSame(120, $metadata['movtime']);
		self::assertSame(1800, $metadata['stptime']);
		self::assertEqualsWithDelta($movingDistance / 120 * 3.6, $metadata['movavgspd'], 0.01);
		self::assertEqualsWithDelta(120 / $movingDistance / 60 * 1000, $metadata['movpace'], 0.01);
	}

	public function testDurationSpansTheFirstToTheLastPoint(): void {
		$metadata = $this->generateMetadata('<trk><trkseg>'
			. '<trkpt lat="45.000" lon="10"><time>2026-01-01T10:00:00Z</time></trkpt>'
			. '<trkpt lat="45.001" lon="10"><time>2026-01-01T10:01:00Z</time></trkpt>'
			. '<trkpt lat="45.002" lon="10"><time>2026-01-01T10:02:00Z</time></trkpt>'
			. '</trkseg></trk>');

		self::assertSame(strtotime('2026-01-01T10:00:00Z'), $metadata['begin']);
		self::assertSame(strtotime('2026-01-01T10:02:00Z'), $metadata['end']);
		self::assertSame(120, $metadata['duration']);
	}

	public function testDurationUsesTheEarliestAndLatestTimeOfUnorderedPoints(): void {
		$metadata = $this->generateMetadata('<rte>'
			. '<rtept lat="45.000" lon="10"><time>2026-01-01T12:00:00Z</time></rtept>'
			. '<rtept lat="45.001" lon="10"><time>2026-01-01T10:00:00Z</time></rtept>'
			. '<rtept lat="45.002" lon="10"><time>2026-01-01T11:00:00Z</time></rtept>'
			. '<rtept lat="45.003" lon="10"><time>2026-01-01T13:00:00Z</time></rtept>'
			. '</rte>');

		self::assertSame(strtotime('2026-01-01T10:00:00Z'), $metadata['begin']);
		self::assertSame(strtotime('2026-01-01T13:00:00Z'), $metadata['end']);
		self::assertSame(3 * 3600, $metadata['duration']);
	}

	/**
	 * @param string $body GPX elements to wrap in a GPX 1.1 document
	 * @return array<string, mixed> the decoded metadata
	 */
	private function generateMetadata(string $body): array {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('test.gpx');
		$file->method('getContent')->willReturn(
			'<?xml version="1.0" encoding="UTF-8"?>'
			. '<gpx version="1.1" creator="test" xmlns="http://www.topografix.com/GPX/1/1">'
			. $body
			. '</gpx>'
		);

		$metadata = $this->service->generateTrackMetadata($file);
		self::assertNotNull($metadata);
		return json_decode($metadata, true, flags: JSON_THROW_ON_ERROR);
	}
}
