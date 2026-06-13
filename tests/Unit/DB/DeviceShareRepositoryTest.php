<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Carl Schwan <carl@carlschwan.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Unit\DB;

use OCA\Maps\DB\DeviceShare;
use OCA\Maps\DB\DeviceShareRepository;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Server;
use PHPUnit\Framework\TestCase;

class DeviceShareRepositoryTest extends TestCase {
	private DeviceShareRepository $repository;
	/** @var list<DeviceShare> */
	private array $created = [];

	public function setUp(): void {
		parent::setUp();
		$this->repository = Server::get(DeviceShareRepository::class);
	}

	public function tearDown(): void {
		foreach ($this->created as $share) {
			try {
				$this->repository->delete($share);
			} catch (\Throwable) {
				// already removed during the test
			}
		}
		$this->created = [];
		parent::tearDown();
	}

	private function createShare(int $deviceId = 1, int $from = 100, int $to = 200): DeviceShare {
		$share = $this->repository->create($deviceId, $from, $to);
		$this->created[] = $share;
		return $share;
	}

	public function testCreate(): void {
		$share = $this->createShare(42, 100, 200);

		$this->assertNotNull($share->id);
		$this->assertNotEmpty($share->token);
		$this->assertEquals(42, $share->deviceId);
		$this->assertEquals(100, $share->timestampFrom);
		$this->assertEquals(200, $share->timestampTo);
	}

	public function testFindByToken(): void {
		$deviceShare = $this->createShare(1, 1, 2);

		$deviceShareDatabase = $this->repository->findByToken($deviceShare->token);
		$this->assertEquals($deviceShare->id, $deviceShareDatabase->id);
		$this->assertEquals($deviceShare->token, $deviceShareDatabase->token);
		$this->assertEquals($deviceShare->timestampFrom, $deviceShareDatabase->timestampFrom);
		$this->assertEquals($deviceShare->timestampTo, $deviceShareDatabase->timestampTo);
	}

	public function testFindByTokenNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->repository->findByToken('nonexistent-token-that-does-not-exist');
	}

	public function testFindById(): void {
		$share = $this->createShare(1, 10, 20);

		$found = $this->repository->findById($share->id);
		$this->assertEquals($share->id, $found->id);
		$this->assertEquals($share->token, $found->token);
		$this->assertEquals($share->deviceId, $found->deviceId);
		$this->assertEquals($share->timestampFrom, $found->timestampFrom);
		$this->assertEquals($share->timestampTo, $found->timestampTo);
	}

	public function testFindByIdNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->repository->findById(-1);
	}

	public function testFindByDeviceIds(): void {
		$share1 = $this->createShare(10, 100, 200);
		$share2 = $this->createShare(10, 300, 400);
		$share3 = $this->createShare(20, 100, 200);

		$shares = iterator_to_array($this->repository->findByDeviceIds([10]));
		$ids = array_map(fn (DeviceShare $s): ?int => $s->id, $shares);

		$this->assertContains($share1->id, $ids);
		$this->assertContains($share2->id, $ids);
		$this->assertNotContains($share3->id, $ids);
	}

	public function testFindByDeviceIdsMultipleDevices(): void {
		$share1 = $this->createShare(11, 100, 200);
		$share2 = $this->createShare(12, 100, 200);
		$share3 = $this->createShare(13, 100, 200);

		$shares = iterator_to_array($this->repository->findByDeviceIds([11, 12]));
		$ids = array_map(fn (DeviceShare $s): ?int => $s->id, $shares);

		$this->assertContains($share1->id, $ids);
		$this->assertContains($share2->id, $ids);
		$this->assertNotContains($share3->id, $ids);
	}

	public function testFindByDeviceIdsEmpty(): void {
		$shares = iterator_to_array($this->repository->findByDeviceIds([]));
		$this->assertEmpty($shares);
	}

	public function testRemoveById(): void {
		$share = $this->createShare(1, 1, 2);
		$id = $share->id;

		$result = $this->repository->removeById($id);
		$this->assertTrue($result);

		$this->expectException(DoesNotExistException::class);
		$this->repository->findById($id);
	}

	public function testRemoveByIdNotFound(): void {
		$result = $this->repository->removeById(-1);
		$this->assertFalse($result);
	}

	public function testRemoveAllByDeviceId(): void {
		$this->createShare(99, 1, 2);
		$this->createShare(99, 3, 4);

		$result = $this->repository->removeAllByDeviceId(99);
		$this->assertTrue($result);

		$remaining = iterator_to_array($this->repository->findByDeviceIds([99]));
		$this->assertEmpty($remaining);
	}

	public function testRemoveAllByDeviceIdNotFound(): void {
		$result = $this->repository->removeAllByDeviceId(-1);
		$this->assertFalse($result);
	}

	public function testCreateTokensAreUnique(): void {
		$share1 = $this->createShare(1, 1, 2);
		$share2 = $this->createShare(1, 1, 2);

		$this->assertNotEquals($share1->token, $share2->token);
	}
}
