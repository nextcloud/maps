/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getSidebar } from '@nextcloud/files'
import { getClient, getDefaultPropfind, getRootPath, resultToNode } from '@nextcloud/files/dav'

type FileStat = Parameters<typeof resultToNode>[0]

/**
 * Open the files sidebar for a file or folder of the current user.
 *
 * @param path path of the node, relative to the user's files root
 * @param tab id of the sidebar tab to show
 */
export async function openFileInSidebar(path: string, tab?: string): Promise<void> {
	const result = await getClient().stat(getRootPath() + path, {
		details: true,
		data: getDefaultPropfind(),
	}) as { data: FileStat }
	getSidebar().open(resultToNode(result.data), tab)
}
