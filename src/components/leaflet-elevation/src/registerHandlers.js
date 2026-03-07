import { Acceleration } from "./handlers/acceleration";
import { Altitude } from "./handlers/altitude";
import { Cadence } from "./handlers/cadence";
import { Distance } from "./handlers/distance";
import { Heart } from "./handlers/heart";
import { Labels } from "./handlers/labels";
import { LinearGradient } from "./handlers/lineargradient";
import { Pace } from "./handlers/pace";
import { Runner } from "./handlers/runner";
import { Slope } from "./handlers/slope";
import { Speed } from "./handlers/speed";
import { Temperature } from "./handlers/temperature";
import { Time } from "./handlers/time";


let registered = false

export function registerElevationHandlers() {

	if (registered) return
	registered = true

	const handlers = [
		Acceleration,
		Altitude,
		Cadence,
		Distance,
		Heart,
		Pace,
		Slope,
		Speed,
		Temperature,
		Time,
	]

	handlers.forEach((handler) => {
		const h = handler.call(L.Control.Elevation.prototype)
		L.Control.Elevation.include({
			[h.name]: h
		})
	})

	// utilities patch the control directly
	Labels.call(L.Control.Elevation.prototype)
	LinearGradient.call(L.Control.Elevation.prototype)
	Runner.call(L.Control.Elevation.prototype)
}