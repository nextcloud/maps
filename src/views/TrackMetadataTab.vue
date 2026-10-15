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
		TrackSidebarMetadataTab,
	},

	props: {
		/**
		 * The GPX file node, set by the files sidebar
		 */
		node: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			error: '',
			loading: false,
			track: null,
		}
	},

	watch: {
		node: {
			immediate: true,
			handler(node) {
				this.track = null
				if (node?.fileid) {
					this.getTrack(node.fileid)
				}
			},
		},
	},

	methods: {
		getTrack(trackFileId) {
			this.loading = true
			const track = {}
			network.getTrack(trackFileId, null, true, getToken()).then((response) => {
				if (this.node?.fileid !== trackFileId) {
					return
				}
				try {
					track.metadata = JSON.parse(response.data.metadata)
				} catch (error) {
					console.error('Failed to parse track metadata')
				}
				track.data = processGpx(response.data.content)
				this.track = track
			}).catch((error) => {
				console.error(error)
				this.error = error
			}).then(() => {
				this.loading = false
			})
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
