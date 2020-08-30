<template>
	<Content app-name="Maps">
		<!--AppNavigation
			/-->
		<AppContent>
			<div id="app-content-wrapper">
				<vl-map ref="map"
					data-projection="EPSG:4326"
					class="themap"
					:load-tiles-while-animating="true"
					:load-tiles-while-interacting="true"
					@created="onMapCreated">
					<vl-view :zoom.sync="zoom" :center.sync="center" :rotation.sync="rotation" />

					<vl-geoloc @update:position="geolocPosition = $event">
						<template slot-scope="geoloc">
							<vl-feature v-if="geoloc.position" id="position-feature">
								<vl-geom-point :coordinates="geoloc.position" />
								<vl-style-box>
									<vl-style-icon src="_media/marker.png" :scale="0.4" :anchor="[0.5, 1]" />
								</vl-style-box>
							</vl-feature>
						</template>
					</vl-geoloc>

					<!--vl-layer-tile id="osm">
						<vl-source-osm />
					</vl-layer-tile-->
					<vl-layer-tile>
						<vl-source-xyz :url="urlRaster2"></vl-source-xyz>
					</vl-layer-tile>
				</vl-map>
			</div>
			<Actions
				class="content-buttons"
				:title="t('maps', 'Details')">
				<ActionButton
					icon="icon-menu-sidebar" />
			</Actions>
		</AppContent>
		<!--Sidebar
			/-->
	</Content>
</template>

<script>
import Vue from 'vue'
import VueLayers from 'vuelayers'
import 'vuelayers/lib/style.css'
// import stylefunction from 'ol-mapbox-style/stylefunction'
import olms from 'ol-mapbox-style'
// import AppNavigation from './components/AppNavigation'
// import Sidebar from './components/Sidebar'
// import * as network from './network'
// import { generateUrl } from '@nextcloud/router'
// import { getCurrentUser } from '@nextcloud/auth'
// import moment from '@nextcloud/moment'
// import {
// showSuccess,
// showError,
// } from '@nextcloud/dialogs'
import {
	Content, AppContent, Actions, ActionButton,
} from '@nextcloud/vue'

Vue.use(VueLayers)

export default {
	name: 'App',
	components: {
		// AppNavigation,
		// Sidebar,
		Content,
		AppContent,
		Actions,
		ActionButton,
	},
	data() {
		return {
			showSidebar: false,
			activeSidebarTab: 'settings',
			zoom: 2,
			center: [0, 0],
			rotation: 0,
			geolocPosition: undefined,
			key: 'pk.eyJ1IjoiZW5laWx1aiIsImEiOiJjazE4Y2xvajcxbGJ6M29xajY1bThuNjRnIn0.hZ4f0_kiPK5OvLBQ1GxVmgg',
		}
	},
	computed: {
		url0() {
			return 'https://api.mapbox.com/styles/v1/mapbox/streets-v11/tiles/256/{z}/{x}/{y}?access_token=' + this.key
		},
		url() {
			return 'https://{a-d}.tiles.mapbox.com/v4/mapbox.mapbox-streets-v6/{z}/{x}/{y}.vector.pbf?access_token=' + this.key
		},
		url2() {
			// ok to get data
			return 'https://api.mapbox.com/v4/mapbox.mapbox-terrain-v2,mapbox.mapbox-streets-v7/{z}/{x}/{y}.vector.pbf?access_token=' + this.key
		},
		urlRaster() {
			return 'https://api.mapbox.com/styles/v1/mapbox/streets-v11/tiles/256/{z}/{x}/{y}?access_token=' + this.key
		},
		urlRaster2() {
			return 'https://api.mapbox.com/styles/v1/mapbox/satellite-streets-v9/tiles/256/{z}/{x}/{y}?access_token=' + this.key
		},
		url3() {
			return 'https://api.mapbox.com/v4/mapbox.mapbox-terrain-v2,mapbox.mapbox-streets-v7.json?secure&access_token=' + this.key
		},
		url4() {
			// ok but olms problems
			return 'https://api.mapbox.com/styles/v1/mapbox/bright-v9?access_token=' + this.key
		},
		url5() {
			return 'https://api.mapbox.com/styles/v1/mapbox/streets-v6?access_token=' + this.key
		},
		url6() {
			return 'https://api.mapbox.com/styles/v1/mapbox/satellite-streets-v9?access_token=' + this.key
		},
		url7() {
			return 'https://{a-d}.tiles.mapbox.com/v4/mapbox.mapbox-streets-v6/{z}/{x}/{y}.vector.pbf?access_token=' + this.key
		},
	},
	mounted() {
	},
	methods: {
		onMapCreated() {
			return
			// this.$refs.map.$map points to underlying OpenLayers ol/Map instance
			olms(this.$refs.map.$map, this.url7)
		},
	},
}
</script>

<style lang="scss" scoped>
.themap {
	height: 100%;
	position: fixed;
}
</style>
