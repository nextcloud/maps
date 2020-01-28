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
	<div class="osm-address">
		<div class="osm-address-text">
			<p v-html="textContents" />
		</div>
		<div class="loading" :class="{ visible: loading }" />
	</div>
</template>

<script>
import Types from '../../data/types'

export default {
	name: 'SimpleOSMAddress',

	props: {
		geocodeObject: Types.OSMGeoCodeResult.def({}),
	},

	computed: {
		loading() {
			return !this.geocodeObject
		},

		textContents() {
			if (!this.geocodeObject) {
				return ''
			}

			if (typeof this.geocodeObject.error !== 'undefined') {
				return t('maps', 'Unknown Place')
			}

			const {
				address: {
					country,
					postcode,
					village,
					pedestrian,
					county,
					state,
					city,
					house_number: houseNumber,
					road,
				},
			} = this.geocodeObject

			const lineFeed = '<br />'
			let address = ''

			if (road) {
				address += `${road} ${houseNumber || ''}${lineFeed}`
			} else if (pedestrian) {
				address += `${pedestrian} ${houseNumber || ''}${lineFeed}`
			}

			if (city) {
				address += `${postcode ? postcode + ' ' : ''}${city}${lineFeed}`
			} else if (village) {
				address += `${postcode ? postcode + ' ' : ''}${village}${lineFeed}`
			}

			if (county) {
				address += `${county}${lineFeed}`
			}

			if (state) {
				address += `${state}${lineFeed}`
			}

			if (country) {
				address += country
			}

			if (address.length === 0) {
				return t('maps', 'Unknown Place')
			}

			return address
		},
	},
}
</script>

<style scoped lang="scss">
$transitionDuration: 0.3s;

.osm-address {
    position: relative;
    width: 100%;

    .osm-address-text {
        width: 100%;
        min-height: 8em;
    }

    .loading {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 0;
        visibility: hidden;
        transition: opacity $transitionDuration, visibility 0s $transitionDuration;
        background: #fff;

        &.visible {
            opacity: 1;
            visibility: visible;
            transition: none;
        }
    }
}
</style>
