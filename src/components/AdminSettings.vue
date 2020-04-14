<template>
	<div>
		<h2>{{ t('maps', 'Maps routing settings') }}</h2>
		<p class="settings-hint">{{ t('maps', 'To enable routing, you must set up a routing engine below.') }}</p>
		<h3><a href="http://project-osrm.org/" :title="t('maps', 'ORSM website')" target="_blank">{{ t('maps', 'OSRM settings') }}</a></h3>
		<p>{{ t('maps', 'An OSRM server URL looks like this : https://my.osrm.server.org:5000/route/v1') }}</p>
		<p>{{ t('maps', 'Leave URL fields empty to disable OSRM routing provider.') }}</p><br/>
		<div id="osrm">
			<label for="osrmCarURL">{{ t('maps', 'OSRM server URL (car profile)') }}</label><br/>
			<input id="osrmCarURL" type="text" :v-model="osrmCarURL"/><br/>

			<label for="osrmBikeURL">{{ t('maps', 'OSRM server URL (bicycle profile)') }}</label><br/>
			<input id="osrmBikeURL" type="text" :v-model="osrmBikeURL"/><br/>

			<label for="osrmFootURL">{{ t('maps', 'OSRM server URL (foot profile)') }}</label><br/>
			<input id="osrmFootURL" type="text" :v-model="osrmFootURL"/><br/>

			<br/>
			<input id="osrmDEMO" type="checkbox" class="checkbox" <?php p($osrmDemoChecked); ?>/>
			<label for="osrmDEMO">{{ t('maps', 'Show OSRM demo server') }}</label>
		</div><br/>

		<h3><a href="https://www.graphhopper.com/" :title="t('maps', 'Graphhopper website')" target="_blank">{{ t('maps', 'GraphHopper settings') }}</a></h3>
		<p>{{ t('maps', 'A GraphHopper server URL looks like this : https://my.graphhopper.server.org:8989/route') }}</p><br />

		<div id="graphhopper">
			<label for="graphhopperURL">{{ t('maps', 'GraphHopper server URL (will use main graphhopper server if empty)') }}</label><br/>
			<input id="graphhopperURL" type="text" :v-model="graphhopperURL"/><br/>
			<label for="graphhopperAPIKEY">{{ t('maps', 'GraphHopper API key (mandatory if main server used)') }}</label><br/>
			<input id="graphhopperAPIKEY" type="text" :v-model="graphhopperAPIKEY"/>
		</div><br/>

		<h3><a href="https://www.mapbox.com/" title="<?php p($l->t('Mapbox Website')) ?>" target="_blank">{{ t('maps', 'Mapbox settings') }}</a></h3>
		<p>{{ t('maps', 'Set the API key to use Mapbox routing service.') }}</p>
		<p>{{ t('maps', 'Leave empty to disable.') }}</p><br/>
		<div id="mapbox">
			<label for="mapboxAPIKEY">{{ t('maps', 'Mapbox API key') }}</label><br/>
			<input id="mapboxAPIKEY" type="text" :v-model="mapboxAPIKEY"/>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state';

export default {
	name: "AdminSettings",
	data() {
		return {
			osrmCarURL: loadState('maps', 'osrmCarURL') || '',
			osrmBikeURL: loadState('maps', 'osrmBikeURL') || '',
			osrmFootURL: loadState('maps', 'osrmFootURL') || '',
			osrmDEMO: loadState('maps', 'osrmDEMO') === '1',
			graphhopperURL: loadState('maps', 'graphhopperURL') || '',
			graphhopperAPIKEY: loadState('maps', 'graphhopperAPIKEY') || '',
			mapboxAPIKEY: loadState('maps', 'mapboxAPIKEY') || '',
		}
	},

}
</script>

<style scoped>

</style>
