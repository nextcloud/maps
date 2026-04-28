<!--
  - @copyright Copyright (c) 2019 Paul Schwörer <hello@paulschwoerer.de>
  -
  - @author Paul Schwörer <hello@paulschwoerer.de>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<NcContent app-name="maps">
		<PublicFavoriteShareSideBar />
		<NcAppContent class="content-wrapper">
			<MapContainer
				:favorite-categories="favoritesMappedByCategory"
				:is-public-share="true"
				:allow-favorite-edits="allowFavoriteEdits"
				@add-favorite="addFavorite"
				@update-favorite="updateFavorite"
				@delete-favorite="deleteFavorite" />
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import MapContainer from '../components/MapContainer.vue'
import PublicFavoriteShareSideBar from '../components/PublicFavoriteShareSideBar.vue'
import { usePublicFavoritesStore } from '../store/publicFavoritesStore.pinia.js'

const favStore = usePublicFavoritesStore()

const favoritesMappedByCategory = computed(() => favStore.mappedByCategory)
const allowFavoriteEdits = computed(() => favStore.shareInfo ? favStore.shareInfo.allowEdits : false)

function addFavorite(data) { favStore.addFavorite(data) }
function updateFavorite(data) { favStore.updateFavorite(data) }
function deleteFavorite(data) { favStore.deleteFavorite(data) }

function moveFooter() {
	const footer = document.getElementsByTagName('footer')[0]
	document.getElementById('app-navigation-vue').appendChild(footer)
}

onMounted(() => {
	favStore.getFavorites()
	moveFooter()
})
</script>

<style lang="scss">
#content {
	height: 100%;
}

* {
	box-sizing: content-box;
}

// Special CSS for placing the footer in the app-navigation
#body-public #app-navigation {
	ul {
		margin-bottom: 70px;
	}

	footer {
		display: block;
		position: absolute;
		bottom: 0;
		width: 100%;
		background-color: var(--color-main-background);
		z-index: 100;
		height: 65px;
	}
}
</style>
