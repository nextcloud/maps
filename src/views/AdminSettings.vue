<!--
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->

<template>
	<NcSettingsSection :name="t('maps', 'Maps routing settings')"
		:description="t('maps', 'To enable routing, you must set up a routing engine below.')">
		<div class="gap">
			<NcFormGroup>
				<template #label>
					<a href="http://project-osrm.org/" target="_blank" rel="noreferrer noopener">
						{{ t('maps', 'OSRM settings') }}
					</a>
				</template>
				<template #description>
					<p>{{ t('maps', 'An OSRM server URL looks like this : https://my.osrm.server.org:5000/route/v1') }}</p>
					<p>{{ t('maps', 'Leave URL fields empty to disable OSRM routing provider.') }}</p>
				</template>

				<NcFormBox>
					<NcTextField :model-value="settings.osrmCarURL"
						:label="t('maps', 'OSRM server URL (car profile)')"
						@update:model-value="onTextFieldChange('osrmCarURL', $event)" />

					<NcTextField :model-value="settings.osrmBikeURL"
						:label="t('maps', 'OSRM server URL (bicycle profile)')"
						@update:model-value="onTextFieldChange('osrmBikeURL', $event)" />

					<NcTextField :model-value="settings.osrmFootURL"
						:label="t('maps', 'OSRM server URL (foot profile)')"
						@update:model-value="onTextFieldChange('osrmFootURL', $event)" />

					<NcFormBoxSwitch :model-value="settings.osrmDEMO"
						:label="t('maps', 'Show OSRM demo server')"
						@update:model-value="onOsrmDemoChange" />
				</NcFormBox>
			</NcFormGroup>

			<NcFormGroup>
				<template #label>
					<a href="https://www.graphhopper.com/" target="_blank" rel="noreferrer noopener">
						{{ t('maps', 'GraphHopper settings') }}
					</a>
				</template>
				<template #description>
					<p>{{ t('maps', 'A GraphHopper server URL looks like this : https://my.graphhopper.server.org:8989/route') }}</p>
				</template>

				<NcFormBox>
					<NcTextField :model-value="settings.graphhopperURL"
						:label="t('maps', 'GraphHopper server URL (will use main graphhopper server if empty)')"
						@update:model-value="onTextFieldChange('graphhopperURL', $event)" />

					<NcTextField :model-value="settings.graphhopperAPIKEY"
						:label="t('maps', 'GraphHopper API key (mandatory if main server used)')"
						@update:model-value="onTextFieldChange('graphhopperAPIKEY', $event)" />
					</NcFormBox>
			</NcFormGroup>

			<NcFormGroup>
				<template #label>
					<a href="https://www.mapbox.com/" target="_blank" rel="noreferrer noopener">
						{{ t('maps', 'Mapbox settings') }}
					</a>
				</template>
				<template #description>
					<p>{{ t('maps', 'Set the API key to use Mapbox routing service.') }}</p>
					<p>{{ t('maps', 'Leave empty to disable.') }}</p>
				</template>

				<NcFormBox>
					<NcTextField :model-value="settings.mapboxAPIKEY"
						:label="t('maps', 'Mapbox API key')"
						@update:model-value="onTextFieldChange('mapboxAPIKEY', $event)" />
				</NcFormBox>
			</NcFormGroup>

			<NcFormGroup>
				<template #label>
					<a href="https://maplibre.org/" target="_blank" rel="noreferrer noopener">
						{{ t('maps', 'MapLibre settings') }}
					</a>
				</template>
				<template #description>
					<p>{{ t('maps', 'Set the URL and Basic Authorization of style.json for OpenStreetMap Vector Tiles with MapLibre-GL-JS.') }}</p>
					<p>{{ t('maps', 'Leave empty to disable.') }}</p>
				</template>

				<NcFormBox>
					<NcTextField :model-value="settings.maplibreStreetStyleURL"
						:label="t('maps', 'MapLibre Street style URL')"
						@update:model-value="onTextFieldChange('maplibreStreetStyleURL', $event)" />

					<NcTextField :model-value="settings.maplibreStreetStyleAuth"
						:label="t('maps', 'Basic Authorization if required. Format is &quot;user:password&quot;')"
						@update:model-value="onTextFieldChange('maplibreStreetStyleAuth', $event)" />

					<NcFormBoxSwitch :model-value="settings.maplibreStreetStylePmtiles"
						:label="t('maps', 'support PMTiles protocol')"
						@update:model-value="onMaplibrePmtilesChange" />
				</NcFormBox>
			</NcFormGroup>
		</div>
	</NcSettingsSection>
</template>

<script setup>
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcFormBox, NcFormBoxSwitch, NcFormGroup, NcSettingsSection, NcTextField } from '@nextcloud/vue'
import { reactive } from 'vue'

const settings = reactive(loadState('maps', 'adminSettings'))

/**
 * Persist a single routing setting and update the local state.
 *
 * @param {string} key the setting key
 * @param {string} value the new value
 */
async function saveSetting(key, value) {
	try {
		await axios.post(generateUrl('/apps/maps/setRoutingSettings'), {
			values: { [key]: value },
		})
		showSuccess(t('maps', 'Settings were successfully saved'))
	} catch (error) {
		showError(t('maps', 'Failed to save settings'))
	}
}

/**
 * @param {string} key the setting key
 * @param {string} value the new field value
 */
function onTextFieldChange(key, value) {
	settings[key] = value
	saveSetting(key, value)
}

/**
 * @param {boolean} value whether the OSRM demo server should be shown
 */
function onOsrmDemoChange(value) {
	settings.osrmDEMO = value
	saveSetting('osrmDEMO', value ? '1' : '0')
}

/**
 * @param {boolean} value whether the PMTiles protocol should be supported
 */
function onMaplibrePmtilesChange(value) {
	settings.maplibreStreetStylePmtiles = value
	saveSetting('maplibreStreetStylePmtiles', value ? '1' : '0')
}
</script>

<style scoped>
.gap {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 6);
}
</style>
