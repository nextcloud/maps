/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * @param unsafe raw text to embed as HTML
 */
export function escapeHtml(unsafe: string): string {
	const div = document.createElement('div')
	div.textContent = unsafe
	return div.innerHTML
}
