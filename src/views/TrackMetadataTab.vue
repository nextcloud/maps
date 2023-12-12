<!--
  - @copyright Copyright (c) 2022 Arne Hamann <git@arne.email>
  -
  - @author Arne Hamann <git@arne.email>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<TrackSidebarMetadataTab v-if="track"
		:track="track" />
</template>

<script>

import TrackSidebarMetadataTab from '../components/TrackMetadataTab.vue'
import * as network from '../network.js'
import { processGpx } from '../tracksUtils.js'
import { getToken } from '../utils/common.js'

export default {
	name: 'TrackMetadataTab',

	components: {
		// Avatar,
		TrackSidebarMetadataTab,
	},

	data() {
		return {
			error: '',
			loading: false,
			track: null,
		}
	},

	computed: {
	},

	methods: {
		/**
		 * Update current ressourceId and fetch new data
		 *
		 * @param {number} ressourceId the current ressourceId (fileId...)
		 * @param trackid
		 * @param trackFileId
		 */
		async update(trackFileId) {
			this.getTrack(trackFileId)
		},

		getTrack(trackFileId) {
			this.loading = true
			const track = {}
			network.getTrack(trackFileId, null, true, getToken()).then((response) => {
				if (!track.metadata) {
					try {
						track.metadata = JSON.parse(response.data.metadata)
					} catch (error) {
						console.error('Failed to parse track metadata')
					}
				}
				track.data = processGpx(response.data.content)
				this.track = track
			}).catch((error) => {
				console.error(error)
				this.error = error
			}).then(() => {
				track.loading = false
			})
		},

		/**
		 * Ran when the bottom of the tab is reached
		 */
		onScrollBottomReached() {
		},

		/**
		 * Reset the current view to its default state
		 */
		resetState() {
			this.error = ''
			this.loading = false
			this.track = null
		},
	},
}
</script>

<style lang="scss" scoped>
.comments {
	// Do not add emptycontent top margin
	&__error{
		margin-top: 0;
	}

	&__info {
		height: 60px;
		color: var(--color-text-maxcontrast);
		text-align: center;
		line-height: 60px;
	}
}
</style>
