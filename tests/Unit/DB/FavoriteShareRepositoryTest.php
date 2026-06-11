<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Carl Schwan <carl@carlschwan.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Unit\DB;

use OCA\Maps\DB\FavoriteShare;
use OCA\Maps\DB\FavoriteShareRepository;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Server;
use PHPUnit\Framework\TestCase;

class FavoriteShareRepositoryTest extends TestCase {
	private FavoriteShareRepository $repository;
	/** @var list<FavoriteShare> */
	private array $created = [];

	public function setUp(): void {
		parent::setUp();
		$this->repository = Server::get(FavoriteShareRepository::class);
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

	private function createShare(string $owner = 'alice', string $category = 'Test'): FavoriteShare {
		$share = $this->repository->create($owner, $category);
		$this->created[] = $share;
		return $share;
	}

	public function testCreate(): void {
		$share = $this->createShare('alice', 'Holidays');

		$this->assertNotNull($share->id);
		$this->assertNotEmpty($share->token);
		$this->assertEquals('alice', $share->owner);
		$this->assertEquals('Holidays', $share->category);
	}

	public function testFindByToken(): void {
		$share = $this->createShare('alice', 'Work');

		$found = $this->repository->findByToken($share->token);
		$this->assertEquals($share->id, $found->id);
		$this->assertEquals($share->token, $found->token);
		$this->assertEquals($share->owner, $found->owner);
		$this->assertEquals($share->category, $found->category);
	}

	public function testFindByTokenNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->repository->findByToken('nonexistent-token-that-does-not-exist');
	}

	public function testFindByOwnerAndCategory(): void {
		$share = $this->createShare('bob', 'Friends');

		$found = $this->repository->findByOwnerAndCategory('bob', 'Friends');
		$this->assertEquals($share->id, $found->id);
		$this->assertEquals($share->token, $found->token);
	}

	public function testFindByOwnerAndCategoryNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->repository->findByOwnerAndCategory('nobody', 'NoSuchCategory');
	}

	public function testFindAllByOwner(): void {
		$share1 = $this->createShare('charlie', 'Cat1');
		$share2 = $this->createShare('charlie', 'Cat2');
		$share3 = $this->createShare('dave', 'Cat1');

		$shares = iterator_to_array($this->repository->findAllByOwner('charlie'));
		$ids = array_map(fn (FavoriteShare $s) => $s->id, $shares);

		$this->assertContains($share1->id, $ids);
		$this->assertContains($share2->id, $ids);
		$this->assertNotContains($share3->id, $ids);
	}

	public function testFindAllByOwnerEmpty(): void {
		$shares = iterator_to_array($this->repository->findAllByOwner('nosuchuser_xyzxyz'));
		$this->assertEmpty($shares);
	}

	public function testFindOrCreateByOwnerAndCategoryCreates(): void {
		$share = $this->repository->findOrCreateByOwnerAndCategory('eve', 'NewCat');
		$this->created[] = $share;

		$this->assertNotNull($share->id);
		$this->assertEquals('eve', $share->owner);
		$this->assertEquals('NewCat', $share->category);
	}

	public function testFindOrCreateByOwnerAndCategoryFindsExisting(): void {
		$existing = $this->createShare('frank', 'ExistingCat');

		$found = $this->repository->findOrCreateByOwnerAndCategory('frank', 'ExistingCat');
		$this->assertEquals($existing->id, $found->id);
		$this->assertEquals($existing->token, $found->token);
	}

	public function testRemoveByOwnerAndCategory(): void {
		$share = $this->createShare('grace', 'ToRemove');

		$result = $this->repository->removeByOwnerAndCategory('grace', 'ToRemove');
		$this->assertTrue($result);

		$this->expectException(DoesNotExistException::class);
		$this->repository->findByOwnerAndCategory('grace', 'ToRemove');
	}

	public function testRemoveByOwnerAndCategoryNotFound(): void {
		$result = $this->repository->removeByOwnerAndCategory('nobody', 'NoSuchCategory');
		$this->assertFalse($result);
	}

	public function testCreateTokensAreUnique(): void {
		$share1 = $this->createShare('heidi', 'Cat1');
		$share2 = $this->createShare('heidi', 'Cat2');

		$this->assertNotEquals($share1->token, $share2->token);
	}
}
