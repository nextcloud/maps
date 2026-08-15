<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Carl Schwan <carl@carlschwan.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Unit\DB;

use OCA\Maps\DB\Geophoto;
use OCA\Maps\DB\GeophotoRepository;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Server;
use PHPUnit\Framework\TestCase;

class GeophotoRepositoryTest extends TestCase {
	private GeophotoRepository $repository;
	/** @var list<Geophoto> */
	private array $created = [];

	public function setUp(): void {
		parent::setUp();
		$this->repository = Server::get(GeophotoRepository::class);
	}

	public function tearDown(): void {
		foreach ($this->created as $geophoto) {
			try {
				$this->repository->delete($geophoto);
			} catch (\Throwable) {
				// already removed during the test
			}
		}
		$this->created = [];
		parent::tearDown();
	}

	private function createGeophoto(
		int $fileId,
		string $userId = 'testuser',
		?float $lat = 48.8566,
		?float $lng = 2.3522,
		?string $dateTaken = '2023-06-01',
	): Geophoto {
		$geophoto = new Geophoto();
		$geophoto->fileId = $fileId;
		$geophoto->userId = $userId;
		$geophoto->lat = $lat;
		$geophoto->lng = $lng;
		$geophoto->dateTaken = $dateTaken !== null ? new \DateTime($dateTaken) : null;

		$geophoto = $this->repository->insert($geophoto);
		$this->created[] = $geophoto;
		return $geophoto;
	}

	public function testFind(): void {
		$geophoto = $this->createGeophoto(1001);

		$found = $this->repository->find($geophoto->id);
		$this->assertEquals($geophoto->id, $found->id);
		$this->assertEquals($geophoto->fileId, $found->fileId);
		$this->assertEquals($geophoto->userId, $found->userId);
		$this->assertEqualsWithDelta($geophoto->lat, $found->lat, 0.0001);
		$this->assertEqualsWithDelta($geophoto->lng, $found->lng, 0.0001);
	}

	public function testFindNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->repository->find(-1);
	}

	public function testFindByFileIdUserId(): void {
		$geophoto = $this->createGeophoto(2001, 'alice');

		$found = $this->repository->findByFileIdUserId(2001, 'alice');
		$this->assertEquals($geophoto->id, $found->id);
		$this->assertEquals(2001, $found->fileId);
		$this->assertEquals('alice', $found->userId);
	}

	public function testFindByFileIdUserIdNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->repository->findByFileIdUserId(-1, 'nobody');
	}

	public function testFindByFileId(): void {
		$geophoto = $this->createGeophoto(3001);

		$found = $this->repository->findByFileId(3001);
		$this->assertEquals($geophoto->id, $found->id);
		$this->assertEquals(3001, $found->fileId);
	}

	public function testFindByFileIdNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->repository->findByFileId(-1);
	}

	public function testFindAllReturnsOnlyLocalized(): void {
		$localized = $this->createGeophoto(4001, 'bob', 51.5, -0.1);
		$nonLocalized = $this->createGeophoto(4002, 'bob', null, null);

		$results = $this->repository->findAll('bob');
		$ids = array_map(fn (Geophoto $g): ?int => $g->id, $results);

		$this->assertContains($localized->id, $ids);
		$this->assertNotContains($nonLocalized->id, $ids);
	}

	public function testFindAllOrderedByDateTaken(): void {
		$older = $this->createGeophoto(5001, 'carol', 1.0, 1.0, '2020-01-01');
		$newer = $this->createGeophoto(5002, 'carol', 2.0, 2.0, '2022-01-01');

		$results = $this->repository->findAll('carol');
		$ids = array_map(fn (Geophoto $g): ?int => $g->id, $results);

		$this->assertContains($older->id, $ids);
		$this->assertContains($newer->id, $ids);
		$olderIndex = array_search($older->id, $ids);
		$newerIndex = array_search($newer->id, $ids);
		$this->assertLessThan($newerIndex, $olderIndex, 'Older photo should come before newer one');
	}

	public function testFindAllWithLimitAndOffset(): void {
		$this->createGeophoto(6001, 'dan', 1.0, 1.0, '2021-01-01');
		$this->createGeophoto(6002, 'dan', 2.0, 2.0, '2022-01-01');
		$this->createGeophoto(6003, 'dan', 3.0, 3.0, '2023-01-01');

		$limited = $this->repository->findAll('dan', limit: 2);
		$this->assertCount(2, $limited);

		$offset = $this->repository->findAll('dan', offset: 1);
		$this->assertCount(2, $offset);
	}

	public function testFindAllNonLocalizedReturnsOnlyNonLocalized(): void {
		$localized = $this->createGeophoto(7001, 'erin', 48.8, 2.3);
		$nonLocalized = $this->createGeophoto(7002, 'erin', null, null);

		$results = $this->repository->findAllNonLocalized('erin');
		$ids = array_map(fn (Geophoto $g): ?int => $g->id, $results);

		$this->assertContains($nonLocalized->id, $ids);
		$this->assertNotContains($localized->id, $ids);
	}

	public function testDeleteByFileId(): void {
		$geophoto = $this->createGeophoto(8001);

		$count = $this->repository->deleteByFileId(8001);
		$this->assertEquals(1, $count);

		$this->expectException(DoesNotExistException::class);
		$this->repository->find($geophoto->id);
	}

	public function testDeleteByFileIdNoneDeleted(): void {
		$count = $this->repository->deleteByFileId(-1);
		$this->assertEquals(0, $count);
	}

	public function testDeleteByFileIdUserId(): void {
		$geophoto = $this->createGeophoto(9001, 'frank');

		$count = $this->repository->deleteByFileIdUserId(9001, 'frank');
		$this->assertEquals(1, $count);

		$this->expectException(DoesNotExistException::class);
		$this->repository->find($geophoto->id);
	}

	public function testDeleteByFileIdUserIdOnlyDeletesMatchingUser(): void {
		$geophoto = $this->createGeophoto(9002, 'grace');

		$count = $this->repository->deleteByFileIdUserId(9002, 'otheruser');
		$this->assertEquals(0, $count);

		$found = $this->repository->find($geophoto->id);
		$this->assertEquals($geophoto->id, $found->id);
	}

	public function testDeleteAll(): void {
		$this->createGeophoto(10001, 'heidi');
		$this->createGeophoto(10002, 'heidi');
		$this->createGeophoto(10003, 'ivan');

		$count = $this->repository->deleteAll('heidi');
		$this->assertEquals(2, $count);

		$remaining = $this->repository->findAll('heidi');
		$this->assertEmpty($remaining);

		$ivanPhotos = $this->repository->findAll('ivan');
		$this->assertCount(1, $ivanPhotos);
	}

	public function testUpdateByFileId(): void {
		$geophoto = $this->createGeophoto(11001, 'judy', null, null);

		$count = $this->repository->updateByFileId(11001, 52.37, 4.90);
		$this->assertEquals(1, $count);

		$updated = $this->repository->find($geophoto->id);
		$this->assertEqualsWithDelta(52.37, $updated->lat, 0.0001);
		$this->assertEqualsWithDelta(4.90, $updated->lng, 0.0001);
	}

	public function testUpdateByFileIdToNull(): void {
		$geophoto = $this->createGeophoto(11002, 'judy', 48.8, 2.3);

		$count = $this->repository->updateByFileId(11002, null, null);
		$this->assertEquals(1, $count);

		$updated = $this->repository->find($geophoto->id);
		$this->assertNull($updated->lat);
		$this->assertNull($updated->lng);
	}

	public function testUpdateByFileIdNoneUpdated(): void {
		$count = $this->repository->updateByFileId(-1, 1.0, 1.0);
		$this->assertEquals(0, $count);
	}
}
