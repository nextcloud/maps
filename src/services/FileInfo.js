/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { getClient, getRemoteURL, getRootPath, resultToNode } from '@nextcloud/files/dav'

/**
 * @param {string} url - Full WebDAV URL of the file
 */
export default async function(url) {
	const remoteURL = getRemoteURL()
	// Strip the remoteURL prefix to get a path relative to the DAV root
	const path = url.startsWith(remoteURL) ? url.slice(remoteURL.length) : getRootPath() + url

	const stat = await getClient().stat(path, { details: true })
	const node = resultToNode(stat.data)

	// Compatibility shim for legacy consumers (Sidebar.vue) that expect the old OC fileInfo shape
	const fileInfo = {
		id: node.fileid,
		name: node.path,
		basename: node.basename,
		mimetype: node.mime,
		mtime: node.mtime,
		size: node.size,
		hasPreview: node.attributes['nc:has-preview'],
		isFavourited: !!node.attributes['oc:favorite'],
		mountType: node.attributes['nc:mount-type'],
		shareTypes: node.attributes['oc:share-types'],
		get(key) { return this[key] },
		isDirectory() { return this.mimetype === 'httpd/unix-directory' },
	}

	return fileInfo
}
