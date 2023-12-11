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
	<div class="popup">
		<h2 v-if="name" class="popup-title">
			{{ name }}
		</h2>

		<div class="popup-content">
			<slot />
		</div>

		<div class="buttons">
			<slot name="buttons" />
		</div>
	</div>
</template>

<script>
import VueTypes from 'vue-types'

export default {
	name: 'Popup',

	props: {
		name: VueTypes.string.def(''),
	},
}
</script>

<style scoped lang="scss">
::v-deep .popup {
	width: 180px;

	.popup-title {
		display: inline-block;
		line-height: 1em;
		color: #333;
		font-size: 1em;
		margin: 0;
		padding: 0;
		white-space: nowrap;
	}

	.popup-content {
		position: relative;
		width: 100%;
	}

	&::v-deep .buttons {
		display: flex;
	}
}
</style>
