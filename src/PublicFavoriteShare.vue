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
	<Content app-name="maps">
		<PublicFavoriteShareSideBar />
		<AppContent class="content-wrapper">
			<MapContainer
				:favorite-categories="favoritesMappedByCategory"
				:is-public-share="true"
				:allow-favorite-edits="allowFavoriteEdits"
				@add-favorite="addFavorite"
				@update-favorite="updateFavorite"
				@delete-favorite="deleteFavorite" />
		</AppContent>
	</Content>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent'
import MapContainer from './components/MapContainer'
import PublicFavoriteShareSideBar from './components/PublicFavoriteShareSideBar'
import { mapActions, mapGetters, mapState } from 'vuex'
import { PUBLIC_FAVORITES_NAMESPACE } from './store/modules/publicFavorites'

export default {
	name: 'PublicFavoriteShare',

	components: {
		NcAppContent,
		NcContent,
		MapContainer,
		PublicFavoriteShareSideBar,
	},

	data() {
		return {
			mode: 'default',
		}
	},

	computed: {
		...mapGetters({
			favoritesMappedByCategory: `${PUBLIC_FAVORITES_NAMESPACE}/mappedByCategory`,
		}),
		...mapState({
			allowFavoriteEdits: state =>
				state[PUBLIC_FAVORITES_NAMESPACE].shareInfo
					? state[PUBLIC_FAVORITES_NAMESPACE].shareInfo.allowEdits
					: false,
		}),
	},

	mounted() {
		this.getFavorites()
		this.moveFooter()
	},

	methods: {
		...mapActions({
			getFavorites: `${PUBLIC_FAVORITES_NAMESPACE}/getFavorites`,
			addFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/addFavorite`,
			updateFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/updateFavorite`,
			deleteFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/deleteFavorite`,
		}),
		// Place the footer in the app-navigation so it is not below the map
		moveFooter() {
			const footer = document.getElementsByTagName('footer')[0]
			document.getElementById('app-navigation-vue').appendChild(footer)
		},
	},
}
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
