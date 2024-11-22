<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Archive;

use Icewind\Streams\CallbackWrapper;
use Psr\Log\LoggerInterface;

class ZIP extends Archive {
	public function __construct(string $source)
 {
 }

	/**
	 * add an empty folder to the archive
	 * @param string $path
	 * @return bool
	 */
	public function addFolder(string $path): bool
 {
 }

	/**
	 * add a file to the archive
	 * @param string $source either a local file or string data
	 */
	public function addFile(string $path, string $source = ''): bool
 {
 }

	/**
	 * rename a file or folder in the archive
	 */
	public function rename(string $source, string $dest): bool
 {
 }

	/**
	 * get the uncompressed size of a file in the archive
	 */
	public function filesize(string $path): false|int|float
 {
 }

	/**
	 * get the last modified time of a file in the archive
	 * @return int|false
	 */
	public function mtime(string $path)
 {
 }

	/**
	 * get the files in a folder
	 */
	public function getFolder(string $path): array
 {
 }

	/**
	 * Generator that returns metadata of all files
	 *
	 * @return \Generator<array>
	 */
	public function getAllFilesStat()
 {
 }

	/**
	 * Return stat information for the given path
	 *
	 * @param string path path to get stat information on
	 * @return ?array stat information or null if not found
	 */
	public function getStat(string $path): ?array
 {
 }

	/**
	 * get all files in the archive
	 */
	public function getFiles(): array
 {
 }

	/**
	 * get the content of a file
	 * @return string|false
	 */
	public function getFile(string $path)
 {
 }

	/**
	 * extract a single file from the archive
	 */
	public function extractFile(string $path, string $dest): bool
 {
 }

	/**
	 * extract the archive
	 */
	public function extract(string $dest): bool
 {
 }

	/**
	 * check if a file or folder exists in the archive
	 */
	public function fileExists(string $path): bool
 {
 }

	/**
	 * remove a file or folder from the archive
	 */
	public function remove(string $path): bool
 {
 }

	/**
	 * get a file handler
	 * @return bool|resource
	 */
	public function getStream(string $path, string $mode)
 {
 }

	/**
	 * write back temporary files
	 */
	public function writeBack(string $tmpFile, string $path): void
 {
 }
}
