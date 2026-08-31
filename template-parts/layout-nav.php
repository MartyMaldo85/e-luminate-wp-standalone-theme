<?php
$assets_base = trailingslashit( get_template_directory_uri() ) . 'assets/';
$home_icon   = $assets_base . 'home.svg';
$close_icon  = $assets_base . 'close.svg';
$menu_icon   = $assets_base . 'menu.svg';
?>
<script>
	/** Orbit center: main (slab) / sub (sans); renderPageSubmenuOrbit swaps them after the first drill (depth ≥ 2). */
	function setCenterSectionTitle(mainLabel, subLabel = '') {
		const titleElm = document.getElementById('orbit-section-title');
		const liveElm = document.getElementById('orbit-section-title-live');
		const mainElm = titleElm ? titleElm.querySelector('.orbit-section-title__main') : null;
		const subElm = titleElm ? titleElm.querySelector('.orbit-section-title__sub') : null;
		if (!titleElm) {
			return;
		}
		const safeMain = mainLabel || '';
		const safeSub = subLabel || '';
		/* renderPageSubmenuOrbit runs every orbit wheel frame via refreshOrbitMotionVisuals; do not rewrite titles or
		 * re-trigger sub enter animation when labels are unchanged (that was restarting keyframes and moving the sans line). */
		const curMain = mainElm ? mainElm.textContent : '';
		const curSub = subElm ? subElm.textContent : '';
		if (curMain === safeMain && curSub === safeSub) {
			return;
		}
		/* data-has-sub before sub text: avoids one frame of absolute translate(-50%,…) vs flex (transition was lerping transform → horizontal flick). */
		titleElm.setAttribute('data-title', safeMain ? 'page-submenu' : '');
		titleElm.setAttribute('data-has-sub', safeSub ? '1' : '0');
		if (mainElm) {
			mainElm.textContent = safeMain;
		}
		if (subElm) {
			subElm.classList.remove('orbit-section-title__sub--enter');
			subElm.textContent = safeSub;
		}
		if (liveElm) {
			liveElm.textContent = safeSub ? `${safeMain} — ${safeSub}` : safeMain;
		}
		if (safeSub && titleElm) {
			const reduceMotion = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			if (!reduceMotion) {
				requestAnimationFrame(() => {
					const sub = titleElm.querySelector('.orbit-section-title__sub');
					if (!sub || !sub.textContent.trim()) {
						return;
					}
					sub.classList.remove('orbit-section-title__sub--enter');
					void sub.offsetWidth;
					sub.classList.add('orbit-section-title__sub--enter');
					const onEnd = (e) => {
						const anim = typeof e.animationName === 'string' ? e.animationName : '';
						if (e.target !== sub || !/orbit-section-title-sub-enter/i.test(anim)) {
							return;
						}
						sub.classList.remove('orbit-section-title__sub--enter');
						sub.removeEventListener('animationend', onEnd);
					};
					sub.addEventListener('animationend', onEnd);
					window.setTimeout(() => {
						sub.classList.remove('orbit-section-title__sub--enter');
						sub.removeEventListener('animationend', onEnd);
					}, 450);
				});
			}
		}
	}

	window.pageMenuStack = Array.isArray(window.pageMenuStack) ? window.pageMenuStack : [];
	/* slot DOM index → ring orbit-i, laid out CCW from LEFT(=0/9 o'clock): bot-left, bot, bot-right, right, top-right, top, top-left. */
	const ORBIT_SLOT_ORDER = [6, 5, 4, 3, 2, 1, 0, 7];
	/** Matches template-parts/orbit-hub.php `$orbit_slots` (physical slot → --orbit-i). */
	const MAIN_ORBIT_SLOT_ORDER = [6, 5, 4, 3, 2, 1, 0, 7];
	const ORBIT_VISIBLE_COUNT = 5;
	const ORBIT_SNAP_DEG = 22.5;
	/** Orbit item stack still advances every 45° (one octagon slot); equals two snap steps. */
	const ORBIT_SNAPS_PER_LABEL_STEP = 2;
	/* visualAngle 0..maxSnap*ORBIT_SNAP_DEG. orbitStepIndex = snap index; labels update when floor(snap/2) changes. */
	window.orbitStepIndex = typeof window.orbitStepIndex === 'number' ? window.orbitStepIndex : 0;
	window.orbitVisualAngle = typeof window.orbitVisualAngle === 'number' ? window.orbitVisualAngle : 0;
	window.orbitSlotItemIdx = Array.isArray(window.orbitSlotItemIdx) ? window.orbitSlotItemIdx : null;
	window.orbitAssignStack = Array.isArray(window.orbitAssignStack) ? window.orbitAssignStack : [];
	function getOrbitMaxStepIndex() {
		const n = getOrbitScrollItemCount();
		return Math.max(0, n - ORBIT_VISIBLE_COUNT);
	}
	function getOrbitMaxSnapIndex() {
		return getOrbitMaxStepIndex() * ORBIT_SNAPS_PER_LABEL_STEP;
	}
	function getOrbitMaxAngleDeg() {
		return getOrbitMaxSnapIndex() * ORBIT_SNAP_DEG;
	}
	/* Wheel: half the px vs 45° snaps so scroll speed matches prior feel at 22.5° snap lattice. */
	window.orbitWheelAccumDy = typeof window.orbitWheelAccumDy === 'number' ? window.orbitWheelAccumDy : 0;
	window.orbitWheelLastTs = typeof window.orbitWheelLastTs === 'number' ? window.orbitWheelLastTs : 0;
	const ORBIT_WHEEL_STEP_ACCUM_PX = 20;
	/** Gap between wheel ticks treated as a new burst (velocity EMA damp only). Slightly loose so OS wheel bursts do not feel like a mid-flick stall. */
	const ORBIT_WHEEL_GESTURE_GAP_MS = 500;
	/** End direct-drive immediately after wheel/touch pause; no extra arm latency. */
	const ORBIT_WHEEL_MOMENTUM_ARM_MS = 0;
	/** No idle wait before settle path checks (snap is disabled anyway). */
	const ORBIT_WHEEL_SNAP_IDLE_MS = 0;
	/** Finger movement (px) before a touch drag arms rotational orbit control (lets taps on ring buttons register). */
	const ORBIT_TOUCH_SLOP_PX = 14;
	window.orbitTouchId = null;
	window.orbitTouchOriginX = 0;
	window.orbitTouchOriginY = 0;
	/** Last atan2 sample (rad); null until first committed drag frame. */
	window.orbitTouchLastAngle = null;
	window.orbitTouchPanning = false;
	/** AbortController for window-level touchmove/end/cancel (finger can leave the spin-pane hit area while orbit-dragging). */
	window.orbitDocTouchAbort = window.orbitDocTouchAbort || null;
	/** Element that holds pointer capture during orbit drag (Pointer Events path). */
	window.orbitPointerCaptureEl = window.orbitPointerCaptureEl || null;
	/** Last good hub/submenu center when getBoundingClientRect() briefly returns a zero-sized box mid-frame. */
	window.orbitGestureCenterCache = window.orbitGestureCenterCache || null;
	/* While true, CSS uses data-motion "free" (no 200ms step tween); MOMENTUM_ARM ends direct-drive, SNAP_IDLE triggers lattice snap if not coasting. */
	window.orbitWheelGestureActive = false;
	window.orbitWheelMomentumArmTimer = window.orbitWheelMomentumArmTimer || null;
	window.orbitWheelSnapIdleTimer = window.orbitWheelSnapIdleTimer || null;
	/* Rubberband + inertia at scroll limits (spring back to 0). */
	window.orbitRubberDeg = typeof window.orbitRubberDeg === 'number' ? window.orbitRubberDeg : 0;
	window.orbitRubberVel = typeof window.orbitRubberVel === 'number' ? window.orbitRubberVel : 0;
	window.orbitRubberRaf = window.orbitRubberRaf || null;
	window.orbitRubberLastTs = typeof window.orbitRubberLastTs === 'number' ? window.orbitRubberLastTs : 0;
	/** Visual asymptote for overscroll (UIScrollView-style rubber band). */
	const ORBIT_RUBBER_MAX_DEG = 14;
	/** Release: stiff, ~critical damping — settles like iOS without wobble. */
	const ORBIT_RUBBER_OMEGA = 60;
	const ORBIT_RUBBER_ZETA = 0.93;
	const ORBIT_RUBBER_SUBSTEPS = 2;
	/** Pull resistance ∝ (1 - |x|/max)^exp — marginal stretch vanishes at limit (iOS feel). */
	const ORBIT_RUBBER_RESIST_EXP = 1.58;
	const ORBIT_RUBBER_PULL_PER_PX = 0.036;
	const ORBIT_RUBBER_VEL_GAIN = 0.088;
	const ORBIT_RUBBER_WALL_VEL_DAMP = 0.18;
	/** Smoothstep display taper near zero (avoids micro-jitter at settle). */
	const ORBIT_RUBBER_DISPLAY_KNEE_DEG = 1.15;
	/* Post-gesture orbit coast (deg/ms); EMA of deltaDeg/dt → launch spin after scroll idle. */
	const ORBIT_MOMENTUM_DRAG = 4.8;
	const ORBIT_MOMENTUM_MIN_VEL = 0.0019;
	const ORBIT_MOMENTUM_LAUNCH = 1.35;
	const ORBIT_MOMENTUM_MAX_VEL = 0.55;
	const ORBIT_MOMENTUM_EDGE_KILL = 0.22;
	/** Cap deg/ms sample into wheel EMA — touch arcs can otherwise spike instVel and jerk momentum/rubber on release. */
	const ORBIT_INSTVEL_CAP = 0.95;
	/** Ignore smaller angle steps (deg) after momentum cancel to cut lift-time micro-jitter / double refresh. */
	const ORBIT_MIN_DELTA_DEG = 0.14;

	window.orbitSpinVel = typeof window.orbitSpinVel === 'number' ? window.orbitSpinVel : 0;
	window.orbitMomentumRaf = window.orbitMomentumRaf || null;
	window.orbitMomentumLastTs = typeof window.orbitMomentumLastTs === 'number' ? window.orbitMomentumLastTs : 0;
	window.orbitWheelVelEma = typeof window.orbitWheelVelEma === 'number' ? window.orbitWheelVelEma : 0;
	window.orbitWheelVelSampleTs = typeof window.orbitWheelVelSampleTs === 'number' ? window.orbitWheelVelSampleTs : 0;
	window.orbitScrollBoundParentKey = typeof window.orbitScrollBoundParentKey === 'string' ? window.orbitScrollBoundParentKey : '';

	function getPageMenuTree() {
		return Array.isArray(window.eluminatePageMenuTree) ? window.eluminatePageMenuTree : [];
	}

	function getOrbitTopicTerms() {
		return Array.isArray(window.eluminateOrbitTopicTerms) ? window.eluminateOrbitTopicTerms : [];
	}

	function getCurrentMenuLayer() {
		const stack = window.pageMenuStack;
		return Array.isArray(stack) && stack.length ? stack[stack.length - 1] : null;
	}

	function getMainOrbitItems() {
		const tree = getPageMenuTree();
		return Array.isArray(tree) ? tree : [];
	}

	function isMainHubOrbitContext() {
		const nav = document.getElementById('body-nav');
		if (!nav || nav.getAttribute('data-menu-open') !== '1') {
			return false;
		}
		return nav.getAttribute('data-selected') !== 'page-submenu';
	}

	function isPageSubmenuOrbitContext() {
		const nav = document.getElementById('body-nav');
		return Boolean(nav && nav.getAttribute('data-selected') === 'page-submenu');
	}

	function isOrbitWheelSurfaceActive() {
		return isMainHubOrbitContext() || isPageSubmenuOrbitContext();
	}

	function getActiveOrbitSlotOrder() {
		return isMainHubOrbitContext() ? MAIN_ORBIT_SLOT_ORDER : ORBIT_SLOT_ORDER;
	}

	/** Child list for orbit scrolling on the main hub or active submenu layer (topic terms or page children). */
	function getOrbitScrollChildren() {
		if (isMainHubOrbitContext()) {
			return getMainOrbitItems();
		}
		const layer = getCurrentMenuLayer();
		if (!layer) {
			return [];
		}
		if ((layer.parentSlug || '') === 'by-topic') {
			return getOrbitTopicTerms();
		}
		return getPageChildrenById(layer.parentId);
	}

	function getOrbitScrollItemCount() {
		return getOrbitScrollChildren().length;
	}

	function setOrbitButtonLabel(elm, label) {
		if (!elm) {
			return;
		}
		const span = document.createElement('span');
		span.className = 'btn-orbit__label';
		span.textContent = `${label || ''}`;
		elm.replaceChildren(span);
	}

	function initByTopicSlotTopicsFromTerms() {
		const n = getOrbitScrollItemCount();
		window.orbitSlotItemIdx = [];
		for (let s = 0; s < 8; s++) {
			window.orbitSlotItemIdx[s] = s < n ? s : -1;
		}
		window.orbitAssignStack = [];
	}

	function resetOrbitMotion() {
		if (window.orbitRubberRaf) {
			window.cancelAnimationFrame(window.orbitRubberRaf);
			window.orbitRubberRaf = null;
		}
		if (window.orbitMomentumRaf) {
			window.cancelAnimationFrame(window.orbitMomentumRaf);
			window.orbitMomentumRaf = null;
		}
		window.orbitSpinVel = 0;
		window.orbitMomentumLastTs = 0;
		window.orbitWheelVelEma = 0;
		window.orbitWheelVelSampleTs = 0;
		window.orbitStepIndex = 0;
		window.orbitVisualAngle = 0;
		window.orbitWheelAccumDy = 0;
		window.orbitWheelLastTs = 0;
		window.orbitWheelGestureActive = false;
		clearOrbitWheelIdleTimers();
		window.orbitRubberDeg = 0;
		window.orbitRubberVel = 0;
		window.orbitRubberLastTs = 0;
		resetOrbitTouchState();
		window.orbitScrollBoundParentKey = '';
		initByTopicSlotTopicsFromTerms();
		const submenuElm = document.getElementById('orbit-submenu-pages');
		if (submenuElm) {
			/* Snap without a long transform tween (e.g. 675° → 0°) when the menu closes or reopens. */
			submenuElm.setAttribute('data-motion', 'free');
			submenuElm.style.setProperty('--orbit-start', '0deg');
			requestAnimationFrame(() => {
				if (!window.orbitWheelGestureActive && !isOrbitRubberActive()) {
					submenuElm.setAttribute('data-motion', 'idle');
				}
			});
		}
		const hubElm = document.getElementById('orbit-hub');
		if (hubElm) {
			hubElm.setAttribute('data-motion', 'free');
			hubElm.style.setProperty('--orbit-start', '0deg');
			requestAnimationFrame(() => {
				if (!window.orbitWheelGestureActive && !isOrbitRubberActive()) {
					hubElm.setAttribute('data-motion', 'idle');
				}
			});
		}
	}

	function getOrbitRubberDegForDisplay() {
		const r = window.orbitRubberDeg || 0;
		const a = Math.abs(r);
		if (a < 1e-7) {
			return 0;
		}
		const knee = ORBIT_RUBBER_DISPLAY_KNEE_DEG;
		const u = Math.min(1, a / knee);
		const s = u * u * (3 - 2 * u);
		return r * s;
	}

	function getOrbitDisplayAngleDeg() {
		return window.orbitVisualAngle + getOrbitRubberDegForDisplay();
	}

	function syncOrbitVisualState(mode = 'idle') {
		const submenuElm = document.getElementById('orbit-submenu-pages');
		if (!submenuElm) {
			return;
		}
		const angleValue = `${getOrbitDisplayAngleDeg()}deg`;
		/* Apply data-motion before --orbit-start so step-mode transform transitions can run (free→step settle). */
		submenuElm.setAttribute('data-motion', mode);
		submenuElm.style.setProperty('--orbit-start', angleValue);
	}

	function refreshOrbitMotionVisuals() {
		if (isPageSubmenuOrbitContext()) {
			renderPageSubmenuOrbit();
		} else if (isMainHubOrbitContext()) {
			renderMainHubOrbit();
		}
	}

	function isOrbitRubberActive() {
		return Math.abs(window.orbitRubberDeg) > 0.06
			|| Math.abs(window.orbitRubberVel) > 0.06
			|| Boolean(window.orbitRubberRaf);
	}

	function isOrbitMomentumActive() {
		return Boolean(window.orbitMomentumRaf)
			|| Math.abs(window.orbitSpinVel) > ORBIT_MOMENTUM_MIN_VEL;
	}

	function kickOrbitMomentum() {
		if (window.orbitMomentumRaf) {
			return;
		}
		window.orbitMomentumLastTs = 0;
		window.orbitMomentumRaf = window.requestAnimationFrame(runOrbitMomentum);
	}

	function runOrbitMomentum(ts) {
		if (!isOrbitWheelSurfaceActive() || getOrbitScrollItemCount() === 0) {
			window.orbitMomentumRaf = null;
			window.orbitSpinVel = 0;
			window.orbitMomentumLastTs = 0;
			return;
		}
		if (getOrbitMaxAngleDeg() <= 0) {
			window.orbitMomentumRaf = null;
			window.orbitSpinVel = 0;
			window.orbitMomentumLastTs = 0;
			return;
		}
		if (window.orbitWheelGestureActive) {
			window.orbitMomentumRaf = null;
			window.orbitSpinVel = 0;
			window.orbitMomentumLastTs = 0;
			return;
		}
		const maxDeg = getOrbitMaxAngleDeg();
		if (maxDeg <= 0 || Math.abs(window.orbitSpinVel) < ORBIT_MOMENTUM_MIN_VEL * 0.5) {
			window.orbitMomentumRaf = null;
			window.orbitSpinVel = 0;
			window.orbitMomentumLastTs = 0;
			snapOrbitToRestingLattice();
			refreshOrbitMotionVisuals();
			return;
		}
		/* First frame used to skip integration (one-frame hitch after wheel arm); use a small bootstrap dt instead. */
		const dt = window.orbitMomentumLastTs === 0
			? 10
			: Math.min(40, Math.max(0, ts - window.orbitMomentumLastTs));
		window.orbitMomentumLastTs = ts;
		let a = window.orbitVisualAngle;
		let v = window.orbitSpinVel;
		a += v * dt;
		if (a >= maxDeg) {
			a = maxDeg;
			if (v > 0) {
				v = 0;
			}
		} else if (a <= 0) {
			a = 0;
			if (v < 0) {
				v = 0;
			}
		}
		window.orbitVisualAngle = a;
		v *= Math.exp(-ORBIT_MOMENTUM_DRAG * dt / 1000);
		window.orbitSpinVel = v;
		syncOrbitSnapAndLabelsFromAngle();
		refreshOrbitMotionVisuals();
		if (Math.abs(v) < ORBIT_MOMENTUM_MIN_VEL) {
			window.orbitSpinVel = 0;
			window.orbitMomentumRaf = null;
			window.orbitMomentumLastTs = 0;
			snapOrbitToRestingLattice();
			refreshOrbitMotionVisuals();
			return;
		}
		window.orbitMomentumRaf = window.requestAnimationFrame(runOrbitMomentum);
	}

	function runOrbitRubber(ts) {
		if (!isOrbitWheelSurfaceActive() || getOrbitScrollItemCount() === 0) {
			window.orbitRubberRaf = null;
			window.orbitRubberVel = 0;
			window.orbitRubberLastTs = 0;
			return;
		}
		if (window.orbitRubberLastTs === 0) {
			window.orbitRubberLastTs = ts;
		}
		const dtMs = Math.min(40, Math.max(0, ts - window.orbitRubberLastTs));
		window.orbitRubberLastTs = ts;
		const dt = dtMs / 1000;
		let x = window.orbitRubberDeg;
		let v = window.orbitRubberVel;
		const w = ORBIT_RUBBER_OMEGA;
		const z = ORBIT_RUBBER_ZETA;
		const w2 = w * w;
		const twoZetaW = 2 * z * w;
		const lim = ORBIT_RUBBER_MAX_DEG;
		const wallDamp = ORBIT_RUBBER_WALL_VEL_DAMP;
		const nSub = Math.max(1, ORBIT_RUBBER_SUBSTEPS);
		const h = dt / nSub;
		for (let step = 0; step < nSub; step++) {
			const accel = -w2 * x - twoZetaW * v;
			v += accel * h;
			x += v * h;
			if (x > lim) {
				x = lim;
				if (v > 0) {
					v = -v * wallDamp;
				}
			} else if (x < -lim) {
				x = -lim;
				if (v < 0) {
					v = -v * wallDamp;
				}
			}
		}
		window.orbitRubberDeg = x;
		window.orbitRubberVel = v;
		refreshOrbitMotionVisuals();
		if (Math.abs(x) < 0.012 && Math.abs(v) < 0.062) {
			window.orbitRubberDeg = 0;
			window.orbitRubberVel = 0;
			window.orbitRubberLastTs = 0;
			window.orbitRubberRaf = null;
			refreshOrbitMotionVisuals();
			return;
		}
		window.orbitRubberRaf = window.requestAnimationFrame(runOrbitRubber);
	}

	function kickOrbitRubberSpring() {
		if (window.orbitRubberRaf) {
			return;
		}
		window.orbitRubberLastTs = 0;
		window.orbitRubberRaf = window.requestAnimationFrame(runOrbitRubber);
	}

	function iosStyleRubberResistance(absRubberDeg) {
		const lim = ORBIT_RUBBER_MAX_DEG;
		const head = Math.min(1, absRubberDeg / lim);
		return Math.pow(Math.max(0, 1 - head), ORBIT_RUBBER_RESIST_EXP);
	}

	function applyOrbitRubberImpulse(dy) {
		const maxDeg = getOrbitMaxAngleDeg();
		const a = window.orbitVisualAngle;
		let touched = false;
		if (maxDeg <= 0) {
			/* No orbit travel: wheel input is pure overscroll rubber (short menus). */
			if (dy !== 0) {
				touched = true;
			}
		} else {
			const atForwardEnd = a >= maxDeg - 0.25;
			const atBackwardEnd = a <= 0.25;
			/* dy<0 = CW push at forward cap; dy>0 = CCW push at backward cap. */
			if (atForwardEnd && dy < 0) {
				touched = true;
			}
			if (atBackwardEnd && dy > 0) {
				touched = true;
			}
		}
		if (!touched) {
			return;
		}
		const resist = iosStyleRubberResistance(Math.abs(window.orbitRubberDeg));
		const pull = (-dy) * ORBIT_RUBBER_PULL_PER_PX * resist;
		const velImp = (-dy) * ORBIT_RUBBER_VEL_GAIN * resist;
		window.orbitRubberDeg += pull;
		window.orbitRubberVel += velImp;
		const lim = ORBIT_RUBBER_MAX_DEG;
		if (window.orbitRubberDeg > lim) {
			window.orbitRubberDeg = lim;
			window.orbitRubberVel = Math.min(window.orbitRubberVel, 0) * 0.45;
		}
		if (window.orbitRubberDeg < -lim) {
			window.orbitRubberDeg = -lim;
			window.orbitRubberVel = Math.max(window.orbitRubberVel, 0) * 0.45;
		}
		if (window.orbitRubberRaf) {
			window.cancelAnimationFrame(window.orbitRubberRaf);
			window.orbitRubberRaf = null;
		}
		window.orbitRubberLastTs = 0;
		refreshOrbitMotionVisuals();
	}

	function clearOrbitWheelIdleTimers() {
		if (window.orbitWheelMomentumArmTimer) {
			window.clearTimeout(window.orbitWheelMomentumArmTimer);
			window.orbitWheelMomentumArmTimer = null;
		}
		if (window.orbitWheelSnapIdleTimer) {
			window.clearTimeout(window.orbitWheelSnapIdleTimer);
			window.orbitWheelSnapIdleTimer = null;
		}
	}

	function maybeKickOrbitRubberAfterWheel() {
		if (Math.abs(window.orbitRubberDeg) > 0.02 || Math.abs(window.orbitRubberVel) > 0.02) {
			kickOrbitRubberSpring();
		}
	}

	/**
	 * Two-phase idle: (1) short arm — end wheel direct-drive and start momentum coast so the ring does not sit
	 * frozen for SNAP_IDLE_MS; (2) long snap — lattice settle after 1s if not already coasting on momentum.
	 */
	function scheduleOrbitWheelGestureEnd() {
		clearOrbitWheelIdleTimers();

		window.orbitWheelMomentumArmTimer = window.setTimeout(() => {
			window.orbitWheelMomentumArmTimer = null;
			window.orbitWheelGestureActive = false;
			const maxDeg = getOrbitMaxAngleDeg();
			let launchedMom = false;
			const ema = window.orbitWheelVelEma;
			if (maxDeg > 0 && Math.abs(ema) >= ORBIT_MOMENTUM_MIN_VEL * 0.35) {
				let v0 = ema * ORBIT_MOMENTUM_LAUNCH;
				v0 = Math.max(-ORBIT_MOMENTUM_MAX_VEL, Math.min(ORBIT_MOMENTUM_MAX_VEL, v0));
				if (Math.abs(v0) >= ORBIT_MOMENTUM_MIN_VEL) {
					window.orbitSpinVel = v0;
					launchedMom = true;
					kickOrbitMomentum();
				}
			}
			window.orbitWheelVelEma = 0;
			window.orbitWheelVelSampleTs = 0;
			if (!launchedMom) {
				refreshOrbitMotionVisuals();
			} else {
				maybeKickOrbitRubberAfterWheel();
			}
		}, ORBIT_WHEEL_MOMENTUM_ARM_MS);

		window.orbitWheelSnapIdleTimer = window.setTimeout(() => {
			window.orbitWheelSnapIdleTimer = null;
			if (isOrbitMomentumActive()) {
				return;
			}
			const snapDeg = window.orbitStepIndex * ORBIT_SNAP_DEG;
			const alreadySnapped = Math.abs(window.orbitVisualAngle - snapDeg) < 0.08
				&& Math.abs(getOrbitRubberDegForDisplay()) < 0.035;
			if (alreadySnapped) {
				return;
			}
			snapOrbitToRestingLattice();
			refreshOrbitMotionVisuals();
			maybeKickOrbitRubberAfterWheel();
		}, ORBIT_WHEEL_SNAP_IDLE_MS);
	}

	/** Update orbit item assignments when discrete step index crosses from prevK to newK (no angle change here). */
	function applyOrbitStepRange(prevK, newK) {
		const n = getOrbitScrollItemCount();
		const max = getOrbitMaxStepIndex();
		if (!Array.isArray(window.orbitSlotItemIdx) || window.orbitSlotItemIdx.length !== 8) {
			initByTopicSlotTopicsFromTerms();
		}
		const prevClamped = Math.max(0, Math.min(max, prevK));
		const newClamped = Math.max(0, Math.min(max, newK));
		if (newClamped === prevClamped) {
			return;
		}
		if (newClamped > prevClamped) {
			for (let k = prevClamped; k < newClamped; k++) {
				const incomingTopic = k + 8;
				if (incomingTopic < n) {
					const s = k % 8;
					window.orbitAssignStack.push({ slot: s, prev: window.orbitSlotItemIdx[s] });
					window.orbitSlotItemIdx[s] = incomingTopic;
				}
			}
		} else {
			for (let k = prevClamped; k > newClamped; k--) {
				const incomingTopic = (k - 1) + 8;
				if (incomingTopic < n && window.orbitAssignStack.length > 0) {
					const top = window.orbitAssignStack.pop();
					if (top && typeof top.slot === 'number') {
						window.orbitSlotItemIdx[top.slot] = top.prev;
					}
				}
			}
		}
	}

	/** Same mapping as applyOrbitStepRange(0, labelStep) from a fresh init; no globals mutated. */
	function computeSlotTopicIdxAtLabelStep(labelStep) {
		const n = getOrbitScrollItemCount();
		const max = getOrbitMaxStepIndex();
		const slotTopicIdx = [];
		for (let s = 0; s < 8; s++) {
			slotTopicIdx[s] = s < n ? s : -1;
		}
		const newClamped = Math.max(0, Math.min(max, labelStep));
		for (let k = 0; k < newClamped; k++) {
			const incomingTopic = k + 8;
			if (incomingTopic < n) {
				const s = k % 8;
				slotTopicIdx[s] = incomingTopic;
			}
		}
		return slotTopicIdx;
	}

	/**
	 * CSS orbit-a = visualAngle + 45°×orbit-i; 90° = 3 o'clock (right). Last list item should rest there at end-of-track.
	 */
	function isLastTopicOrbitAtThreeOClock(snapIndex) {
		const n = getOrbitScrollItemCount();
		if (n <= ORBIT_VISIBLE_COUNT) {
			return true;
		}
		const labelStep = Math.floor(snapIndex / ORBIT_SNAPS_PER_LABEL_STEP);
		const slotTopicIdx = computeSlotTopicIdxAtLabelStep(labelStep);
		let slot = -1;
		for (let i = 0; i < 8; i++) {
			if (slotTopicIdx[i] === n - 1) {
				slot = i;
				break;
			}
		}
		if (slot < 0) {
			return true;
		}
		const oi = getActiveOrbitSlotOrder()[slot];
		const a = snapIndex * ORBIT_SNAP_DEG;
		let phi = (a + 45 * oi) % 360;
		if (phi < 0) {
			phi += 360;
		}
		const d = Math.min(Math.abs(phi - 90), 360 - Math.abs(phi - 90));
		return d < 0.25;
	}

	/**
	 * Penultimate snap (one 22.5° step before terminal) leaves the last topic between slots; bump to terminal so it rests at 3 o'clock only.
	 */
	function nudgePenultimateLastOrbitItemSnapToThreeOClock() {
		const maxSnap = getOrbitMaxSnapIndex();
		if (maxSnap <= 0) {
			return;
		}
		const n = getOrbitScrollItemCount();
		if (n <= ORBIT_VISIBLE_COUNT) {
			return;
		}
		const snap = window.orbitStepIndex;
		if (snap !== maxSnap - 1) {
			return;
		}
		if (isLastTopicOrbitAtThreeOClock(snap)) {
			return;
		}
		const labelStep = Math.floor(snap / ORBIT_SNAPS_PER_LABEL_STEP);
		const slots = computeSlotTopicIdxAtLabelStep(labelStep);
		const itemCount = getOrbitScrollItemCount();
		let hasLast = false;
		for (let i = 0; i < 8; i++) {
			if (slots[i] === itemCount - 1) {
				hasLast = true;
				break;
			}
		}
		if (!hasLast) {
			return;
		}
		window.orbitVisualAngle = maxSnap * ORBIT_SNAP_DEG;
		syncOrbitSnapAndLabelsFromAngle();
	}

	function snapOrbitToRestingLattice() {
		/* Snap disabled: keep continuous angle where gesture/momentum leaves it. */
		return;
	}

	function syncOrbitStepIndexFromAngle() {
		const maxSnap = getOrbitMaxSnapIndex();
		const maxDeg = getOrbitMaxAngleDeg();
		const a = Math.min(maxDeg, Math.max(0, window.orbitVisualAngle));
		return Math.min(maxSnap, Math.max(0, Math.floor((a + 1e-6) / ORBIT_SNAP_DEG)));
	}

	function syncOrbitSnapAndLabelsFromAngle() {
		const prevSnap = window.orbitStepIndex;
		const newSnap = syncOrbitStepIndexFromAngle();
		const prevLab = Math.floor(prevSnap / ORBIT_SNAPS_PER_LABEL_STEP);
		const newLab = Math.floor(newSnap / ORBIT_SNAPS_PER_LABEL_STEP);
		if (newLab !== prevLab) {
			applyOrbitStepRange(prevLab, newLab);
		}
		window.orbitStepIndex = newSnap;
	}

	function wheelDeltaYToPixels(event) {
		if (!event) {
			return 0;
		}
		if (event.deltaMode === 1) {
			return event.deltaY * 16;
		}
		if (event.deltaMode === 2) {
			return event.deltaY * 800;
		}
		return event.deltaY;
	}

	/**
	 * Apple pointer behavior uses natural scrolling (scroll "down" → negative deltaY).
	 * Typical Windows/Linux wheel: scroll down → positive deltaY.
	 * Orbit spec: scroll down = clockwise for everyone.
	 */
	function isMacLikeWheelPlatform() {
		if (typeof navigator === 'undefined') {
			return false;
		}
		if (navigator.userAgentData && typeof navigator.userAgentData.platform === 'string') {
			const p = navigator.userAgentData.platform;
			return p === 'macOS' || p === 'iOS' || p === 'iPadOS';
		}
		return /Mac|iPhone|iPad|iPod/i.test(navigator.userAgent || navigator.platform || '');
	}

	function alignRawDeltaYToOrbitSpec(rawDy) {
		return isMacLikeWheelPlatform() ? rawDy : -rawDy;
	}

	function wheelDeltaYAlignedToOrbitSpec(event) {
		return alignRawDeltaYToOrbitSpec(wheelDeltaYToPixels(event));
	}

	/**
	 * Apply a change to the orbit scroll angle (deg). `dyForRubber` is the equivalent wheel delta (px space) for rubber-band at ends.
	 * Wheel path: deltaDeg from dy; touch path: deltaDeg from finger arc around the ring center.
	 */
	function applyOrbitGestureDeltaDeg(deltaDeg, dyForRubber, timeStamp) {
		if (!isOrbitWheelSurfaceActive() || getOrbitScrollItemCount() === 0) {
			return;
		}
		window.orbitWheelGestureActive = true;
		clearOrbitWheelIdleTimers();
		const now = timeStamp || performance.now();
		const prevTs = window.orbitWheelLastTs;
		if (prevTs && now - prevTs > ORBIT_WHEEL_GESTURE_GAP_MS) {
			window.orbitWheelAccumDy = 0;
			window.orbitWheelVelEma *= 0.62;
		}
		window.orbitWheelLastTs = now;
		if (window.orbitMomentumRaf) {
			window.cancelAnimationFrame(window.orbitMomentumRaf);
			window.orbitMomentumRaf = null;
		}
		window.orbitSpinVel = 0;
		window.orbitMomentumLastTs = 0;
		const maxDeg = getOrbitMaxAngleDeg();
		if (maxDeg <= 0) {
			applyOrbitRubberImpulse(dyForRubber);
			scheduleOrbitWheelGestureEnd();
			return;
		}
		if (Math.abs(deltaDeg) < ORBIT_MIN_DELTA_DEG) {
			scheduleOrbitWheelGestureEnd();
			return;
		}
		const prevAngle = window.orbitVisualAngle;
		let nextAngle = prevAngle + deltaDeg;
		nextAngle = Math.min(maxDeg, Math.max(0, nextAngle));
		const angleChanged = nextAngle !== prevAngle;
		if (angleChanged) {
			const tVel = timeStamp || now;
			const dtVel = Math.max(5, tVel - (window.orbitWheelVelSampleTs || tVel));
			window.orbitWheelVelSampleTs = tVel;
			const rawInstVel = deltaDeg / dtVel;
			const instVel = Math.max(
				-ORBIT_INSTVEL_CAP,
				Math.min(ORBIT_INSTVEL_CAP, rawInstVel)
			);
			window.orbitWheelVelEma = window.orbitWheelVelEma * 0.78 + instVel * 0.22;
			window.orbitVisualAngle = nextAngle;
			window.orbitRubberDeg = 0;
			window.orbitRubberVel = 0;
			if (window.orbitRubberRaf) {
				window.cancelAnimationFrame(window.orbitRubberRaf);
				window.orbitRubberRaf = null;
			}
			window.orbitRubberLastTs = 0;
			syncOrbitSnapAndLabelsFromAngle();
			refreshOrbitMotionVisuals();
		} else {
			applyOrbitRubberImpulse(dyForRubber);
		}
		scheduleOrbitWheelGestureEnd();
	}

	/** `dy` is already in the same space as wheelDeltaYAlignedToOrbitSpec output. */
	function applyOrbitWheelDyPixels(dy, timeStamp) {
		const deltaDeg = (dy / ORBIT_WHEEL_STEP_ACCUM_PX) * ORBIT_SNAP_DEG;
		/* Wheel direction is reversed for spin; rubber edge tests still expect the pre-reversal dy sign. */
		applyOrbitGestureDeltaDeg(deltaDeg, -dy, timeStamp);
	}

	function deltaDegToRubberDy(deltaDeg) {
		return (-deltaDeg * ORBIT_WHEEL_STEP_ACCUM_PX) / ORBIT_SNAP_DEG;
	}

	function unwrapAngleRad(d) {
		let x = d;
		while (x > Math.PI) {
			x -= 2 * Math.PI;
		}
		while (x < -Math.PI) {
			x += 2 * Math.PI;
		}
		return x;
	}

	/** Center of the active orbit ring (viewport px). */
	function getOrbitGestureCenter() {
		const sub = document.getElementById('orbit-submenu-pages');
		const hub = document.getElementById('orbit-hub');
		const pick = isPageSubmenuOrbitContext() ? sub : hub;
		const el = pick && pick.getBoundingClientRect().width > 2 ? pick : (hub || sub);
		if (!el) {
			return null;
		}
		const r = el.getBoundingClientRect();
		if (r.width < 2 || r.height < 2) {
			return null;
		}
		return { cx: r.left + r.width * 0.5, cy: r.top + r.height * 0.5 };
	}

	function getOrbitGestureCenterLiveOrCached() {
		const live = getOrbitGestureCenter();
		if (live) {
			window.orbitGestureCenterCache = live;
			return live;
		}
		return window.orbitGestureCenterCache || null;
	}

	/** atan2 from client point to center; clamps radius so passing over the hub does not spike. */
	function orbitTouchAngleFromClientXY(clientX, clientY, c) {
		let rx = clientX - c.cx;
		let ry = clientY - c.cy;
		const r = Math.hypot(rx, ry);
		const rMin = 28;
		if (r < 1e-4) {
			return null;
		}
		if (r < rMin) {
			const s = rMin / r;
			rx *= s;
			ry *= s;
		}
		return Math.atan2(ry, rx);
	}

	function handleOrbitWheel(event) {
		applyOrbitWheelDyPixels(wheelDeltaYAlignedToOrbitSpec(event), event.timeStamp || performance.now());
	}

	function resetOrbitTouchState() {
		if (window.orbitDocTouchAbort) {
			window.orbitDocTouchAbort.abort();
			window.orbitDocTouchAbort = null;
		}
		if (window.orbitPointerCaptureEl != null && window.orbitTouchId != null) {
			try {
				window.orbitPointerCaptureEl.releasePointerCapture(window.orbitTouchId);
			} catch (_) {
			}
		}
		window.orbitPointerCaptureEl = null;
		window.orbitGestureCenterCache = null;
		window.orbitTouchId = null;
		window.orbitTouchPanning = false;
		window.orbitTouchLastAngle = null;
	}

	function findOrbitTouchById(touchList, id) {
		for (let i = 0; i < touchList.length; i++) {
			if (touchList[i].identifier === id) {
				return touchList[i];
			}
		}
		return null;
	}

	/** Prefer TouchEvent.touches — targetTouches can omit the finger after retargeting outside the listener subtree. */
	function getTrackedOrbitTouch(e) {
		if (window.orbitTouchId === null) {
			return null;
		}
		return (
			findOrbitTouchById(e.touches, window.orbitTouchId) ||
			findOrbitTouchById(e.targetTouches, window.orbitTouchId)
		);
	}

	function orbitHandleDragMove(clientX, clientY, activeFingerCount, timeStamp, moveEvent) {
		const c = getOrbitGestureCenterLiveOrCached();
		if (!c) {
			return;
		}
		if (!window.orbitTouchPanning) {
			if (activeFingerCount > 1) {
				resetOrbitTouchState();
				return;
			}
			const rdx = clientX - window.orbitTouchOriginX;
			const rdy = clientY - window.orbitTouchOriginY;
			if (Math.hypot(rdx, rdy) < ORBIT_TOUCH_SLOP_PX) {
				return;
			}
			window.orbitTouchPanning = true;
			moveEvent.preventDefault();
			const a0 = orbitTouchAngleFromClientXY(window.orbitTouchOriginX, window.orbitTouchOriginY, c);
			const a1 = orbitTouchAngleFromClientXY(clientX, clientY, c);
			if (a0 === null || a1 === null) {
				window.orbitTouchLastAngle = a1 !== null ? a1 : a0;
				return;
			}
			const dRad = unwrapAngleRad(a1 - a0);
			const deltaDeg = dRad * (180 / Math.PI);
			window.orbitTouchLastAngle = a1;
			applyOrbitGestureDeltaDeg(deltaDeg, deltaDegToRubberDy(deltaDeg), timeStamp || performance.now());
			return;
		}
		moveEvent.preventDefault();
		const aPrev = window.orbitTouchLastAngle;
		const aNow = orbitTouchAngleFromClientXY(clientX, clientY, c);
		if (aNow === null) {
			return;
		}
		if (aPrev === null || aPrev === undefined) {
			window.orbitTouchLastAngle = aNow;
			return;
		}
		const dRad = unwrapAngleRad(aNow - aPrev);
		window.orbitTouchLastAngle = aNow;
		const deltaDeg = dRad * (180 / Math.PI);
		if (deltaDeg !== 0) {
			applyOrbitGestureDeltaDeg(deltaDeg, deltaDegToRubberDy(deltaDeg), timeStamp || performance.now());
		}
	}

	function bindOrbitWindowTouchTracking() {
		if (window.orbitDocTouchAbort) {
			window.orbitDocTouchAbort.abort();
		}
		const ac = new AbortController();
		window.orbitDocTouchAbort = ac;
		const sig = ac.signal;
		window.addEventListener('touchmove', onOrbitTouchMove, { capture: true, passive: false, signal: sig });
		window.addEventListener('touchend', onOrbitTouchEnd, { capture: true, passive: true, signal: sig });
		window.addEventListener('touchcancel', onOrbitTouchCancel, { capture: true, passive: true, signal: sig });
	}

	function onOrbitTouchStart(e) {
		resetOrbitTouchState();
		if (!isOrbitWheelSurfaceActive() || getOrbitScrollItemCount() === 0) {
			return;
		}
		if (e.targetTouches.length !== 1) {
			return;
		}
		const t = e.targetTouches[0];
		window.orbitTouchId = t.identifier;
		window.orbitTouchOriginX = t.clientX;
		window.orbitTouchOriginY = t.clientY;
		window.orbitTouchLastAngle = null;
		window.orbitTouchPanning = false;
		bindOrbitWindowTouchTracking();
	}

	function onOrbitTouchMove(e) {
		if (window.orbitTouchId === null) {
			return;
		}
		const t = getTrackedOrbitTouch(e);
		if (!t) {
			return;
		}
		const ts = e.timeStamp || performance.now();
		orbitHandleDragMove(t.clientX, t.clientY, e.touches.length, ts, e);
	}

	function onOrbitPointerDown(e) {
		if (e.pointerType === 'mouse') {
			return;
		}
		resetOrbitTouchState();
		if (!isOrbitWheelSurfaceActive() || getOrbitScrollItemCount() === 0) {
			return;
		}
		if (!e.isPrimary) {
			return;
		}
		window.orbitTouchId = e.pointerId;
		window.orbitPointerCaptureEl = e.currentTarget;
		window.orbitTouchOriginX = e.clientX;
		window.orbitTouchOriginY = e.clientY;
		window.orbitTouchLastAngle = null;
		window.orbitTouchPanning = false;
		try {
			e.currentTarget.setPointerCapture(e.pointerId);
		} catch (_) {
		}
	}

	function onOrbitPointerMove(e) {
		if (window.orbitTouchId === null || e.pointerId !== window.orbitTouchId) {
			return;
		}
		const ts = e.timeStamp || performance.now();
		orbitHandleDragMove(e.clientX, e.clientY, 1, ts, e);
	}

	function onOrbitPointerUp(e) {
		if (window.orbitTouchId === null || e.pointerId !== window.orbitTouchId) {
			return;
		}
		if (window.orbitTouchPanning) {
			window.orbitWheelVelEma *= 0.5;
			scheduleOrbitWheelGestureEnd();
		}
		resetOrbitTouchState();
	}

	function onOrbitPointerCancel(e) {
		if (window.orbitTouchId === null || e.pointerId !== window.orbitTouchId) {
			return;
		}
		if (window.orbitTouchPanning) {
			window.orbitWheelVelEma *= 0.5;
			scheduleOrbitWheelGestureEnd();
		}
		resetOrbitTouchState();
	}

	function onOrbitTouchEnd(e) {
		if (window.orbitTouchId === null) {
			return;
		}
		const ended = findOrbitTouchById(e.changedTouches, window.orbitTouchId);
		if (!ended) {
			return;
		}
		if (window.orbitTouchPanning) {
			/* Damp lift-time noise in velocity estimate so settle/momentum does not hitch on the last samples. */
			window.orbitWheelVelEma *= 0.5;
			scheduleOrbitWheelGestureEnd();
		}
		resetOrbitTouchState();
	}

	function onOrbitTouchCancel() {
		if (window.orbitTouchPanning) {
			window.orbitWheelVelEma *= 0.5;
			scheduleOrbitWheelGestureEnd();
		}
		resetOrbitTouchState();
	}

	function findPageNodeById(nodes, id) {
		const wantedId = Number(id);
		if (!Array.isArray(nodes) || !Number.isInteger(wantedId)) {
			return null;
		}
		for (const node of nodes) {
			if (!node || typeof node !== 'object') {
				continue;
			}
			if (Number(node.id) === wantedId) {
				return node;
			}
			const childHit = findPageNodeById(node.children || [], wantedId);
			if (childHit) {
				return childHit;
			}
		}
		return null;
	}

	function getPageChildrenById(parentId) {
		const tree = getPageMenuTree();
		if (!Number.isInteger(Number(parentId))) {
			return tree;
		}
		const parentNode = findPageNodeById(tree, parentId);
		return parentNode && Array.isArray(parentNode.children) ? parentNode.children : [];
	}

	function setActiveMainButton(mainId) {
		document.querySelectorAll('#orbit-hub .btn-main-menu').forEach((elm) => elm.classList.remove('active'));
		if (!Number.isInteger(Number(mainId))) {
			return;
		}
		const active = document.querySelector(`#orbit-hub .btn-main-menu[data-page-id="${Number(mainId)}"]`);
		if (active) {
			active.classList.add('active');
		}
	}

	function triggerOrbitSectionTitleEnterFromMain() {
		const titleElm = document.getElementById('orbit-section-title');
		if (!titleElm) {
			return;
		}
		if (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}
		requestAnimationFrame(() => {
			titleElm.classList.remove('orbit-section-title--enter');
			void titleElm.offsetWidth;
			titleElm.style.removeProperty('opacity');
			titleElm.classList.add('orbit-section-title--enter');
			const onEnd = (e) => {
				if (e.target !== titleElm || e.animationName !== 'orbit-section-title-enter') {
					return;
				}
				titleElm.classList.remove('orbit-section-title--enter');
				titleElm.style.removeProperty('opacity');
				titleElm.removeEventListener('animationend', onEnd);
			};
			titleElm.addEventListener('animationend', onEnd);
		});
	}

	/** Fade + slide the topic orbit ring (same keyframes as slab title). */
	function triggerOrbitSubmenuPagesEnter(submenuElm) {
		if (!submenuElm) {
			return;
		}
		if (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			submenuElm.style.removeProperty('opacity');
			return;
		}
		requestAnimationFrame(() => {
			submenuElm.classList.remove('orbit-submenu-pages--enter');
			void submenuElm.offsetWidth;
			submenuElm.classList.add('orbit-submenu-pages--enter');
			const onEnd = (e) => {
				const anim = typeof e.animationName === 'string' ? e.animationName : '';
				if (e.target !== submenuElm || !/orbit-submenu-pages-enter/i.test(anim)) {
					return;
				}
				submenuElm.classList.remove('orbit-submenu-pages--enter');
				submenuElm.style.removeProperty('opacity');
				submenuElm.removeEventListener('animationend', onEnd);
			};
			submenuElm.addEventListener('animationend', onEnd);
			window.setTimeout(() => {
				submenuElm.classList.remove('orbit-submenu-pages--enter');
				submenuElm.style.removeProperty('opacity');
				submenuElm.removeEventListener('animationend', onEnd);
			}, 500);
		});
	}

	function openPageSubmenuFromMainOrbitNode(node) {
		const bodyNavElm = document.getElementById('body-nav');
		if (!bodyNavElm || !node) {
			return;
		}
		const pageId = Number(node.id);
		const pageLabel = `${node.title || ''}`;
		const pageSlug = `${node.slug || ''}`;
		const titleElm = document.getElementById('orbit-section-title');
		const titleEnter = typeof window.matchMedia === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (titleElm && titleEnter) {
			titleElm.style.setProperty('opacity', '0');
		}
		bodyNavElm.setAttribute('data-menu-open', '1');
		hideSiteLogo();
		window.pageMenuStack = [{
			mainId: pageId,
			mainLabel: pageLabel,
			parentId: pageId,
			parentSlug: pageSlug,
			parentLabel: pageLabel,
		}];
		/* Before resetOrbitMotion: orbit scroll count must reflect submenu, not hub (slot indices / >5 items). */
		bodyNavElm.setAttribute('data-selected', 'page-submenu');
		resetOrbitMotion();
		renderPageSubmenuOrbit({ submenuEnter: true, orbitTitleEnterFromHub: true });
	}

	function renderMainHubOrbit() {
		const bodyNavElm = document.getElementById('body-nav');
		const hub = document.getElementById('orbit-hub');
		if (!bodyNavElm || !hub || bodyNavElm.getAttribute('data-menu-open') !== '1') {
			return;
		}
		if (bodyNavElm.getAttribute('data-selected') === 'page-submenu') {
			return;
		}
		const items = getMainOrbitItems();
		const orbitScrollMode = items.length > ORBIT_VISIBLE_COUNT;
		const orbitRubberUi = items.length > 0;

		if (orbitRubberUi) {
			if (orbitScrollMode) {
				const parentKey = 'main-hub';
				if (window.orbitScrollBoundParentKey !== parentKey) {
					resetOrbitMotion();
					window.orbitScrollBoundParentKey = parentKey;
				}
			} else {
				window.orbitScrollBoundParentKey = '';
			}
			const orbitSnapped = Math.abs(window.orbitVisualAngle - window.orbitStepIndex * ORBIT_SNAP_DEG) < 0.01
				&& Math.abs(getOrbitRubberDegForDisplay()) < 0.035;
			const useFreeMotion = window.orbitWheelGestureActive || isOrbitRubberActive()
				|| isOrbitMomentumActive() || !orbitSnapped;
			const mode = useFreeMotion ? 'free' : (window.orbitVisualAngle !== 0 ? 'step' : 'idle');
			/* data-motion first so leaving "free" picks up transform easing before the angle updates. */
			hub.setAttribute('data-motion', mode);
			hub.style.setProperty('--orbit-start', `${getOrbitDisplayAngleDeg()}deg`);
		} else {
			window.orbitScrollBoundParentKey = '';
			hub.setAttribute('data-motion', 'idle');
			hub.style.setProperty('--orbit-start', '0deg');
		}

		if (orbitScrollMode && (!Array.isArray(window.orbitSlotItemIdx) || window.orbitSlotItemIdx.length !== 8)) {
			initByTopicSlotTopicsFromTerms();
		}

		const cells = Array.from(hub.children);
		for (let slot = 0; slot < 8; slot++) {
			let cell = cells[slot];
			const oi = MAIN_ORBIT_SLOT_ORDER[slot] ?? slot;
			if (!cell || cell.tagName !== 'BUTTON') {
				const b = document.createElement('button');
				b.type = 'button';
				if (cell && cell.parentNode === hub) {
					hub.replaceChild(b, cell);
				} else {
					hub.appendChild(b);
				}
				cell = b;
				cells[slot] = cell;
			}
			cell.style.setProperty('--orbit-i', String(oi));

			let itemIndex;
			if (orbitScrollMode) {
				itemIndex = window.orbitSlotItemIdx[slot];
			} else {
				itemIndex = slot < items.length ? slot : -1;
			}
			const node = (itemIndex >= 0 && itemIndex < items.length) ? items[itemIndex] : null;

			if (!node) {
				cell.className = 'btn btn-orbit btn-orbit--phantom btn-hub-slot';
				cell.removeAttribute('data-page-id');
				cell.removeAttribute('data-nav-mode');
				cell.removeAttribute('data-page-slug');
				cell.removeAttribute('data-page-label');
				cell.removeAttribute('data-target-url');
				cell.removeAttribute('onclick');
				cell.textContent = '';
				cell.setAttribute('aria-hidden', 'true');
				cell.style.pointerEvents = 'none';
				cell.onclick = null;
				continue;
			}

			const topicDrilldown = (node.slug || '') === 'by-topic' && getOrbitTopicTerms().length > 0;
			const hasChildren = topicDrilldown || (Array.isArray(node.children) && node.children.length > 0);
			const pageId = Number(node.id);
			cell.className = `btn btn-main-menu btn-orbit page-${pageId}`;
			cell.setAttribute('data-nav-mode', hasChildren ? 'submenu' : 'link');
			cell.setAttribute('data-page-id', String(pageId));
			cell.setAttribute('data-page-slug', node.slug || '');
			cell.setAttribute('data-page-label', node.title || '');
			cell.setAttribute('data-target-url', node.url || '');
			cell.removeAttribute('onclick');
			cell.setAttribute('aria-hidden', 'false');
			cell.style.pointerEvents = 'auto';
			setOrbitButtonLabel(cell, node.title || '');
			cell.onclick = (event) => {
				event.preventDefault();
				if (hasChildren) {
					openPageSubmenuFromMainOrbitNode(node);
					return;
				}
				closeFullNav();
				window.location.href = node.url || '#';
			};
		}
	}

	function initOrbitWheel() {
		if (window.orbitWheelAbort) {
			window.orbitWheelAbort.abort();
		}
		resetOrbitTouchState();
		const ac = new AbortController();
		window.orbitWheelAbort = ac;
		window.addEventListener('wheel', (event) => {
			const bodyNavElm = document.getElementById('body-nav');
			let orbitReject = '';
			if (!bodyNavElm || bodyNavElm.getAttribute('data-menu-open') !== '1') {
				orbitReject = 'menu_closed';
			} else if (!isOrbitWheelSurfaceActive()) {
				orbitReject = 'no_orbit_surface';
			} else if (getOrbitScrollItemCount() === 0) {
				orbitReject = 'no_orbit_items';
			}
			if (orbitReject) {
				return;
			}
			event.preventDefault();
			handleOrbitWheel(event);
		}, { passive: false, signal: ac.signal });
		const orbitTouchRoot = document.querySelector('.header-logo-cluster');
		if (orbitTouchRoot) {
			if (typeof window.PointerEvent !== 'undefined') {
				/*
				 * Pointer capture keeps pointermove targeted at this node after the finger leaves the spin pane
				 * (.header-logo-cluster is pointer-events:none except .header-logo-cluster__spin-pane).
				 */
				orbitTouchRoot.addEventListener('pointerdown', onOrbitPointerDown, { passive: true, signal: ac.signal });
				orbitTouchRoot.addEventListener('pointermove', onOrbitPointerMove, { passive: false, signal: ac.signal });
				orbitTouchRoot.addEventListener('pointerup', onOrbitPointerUp, { passive: true, signal: ac.signal });
				orbitTouchRoot.addEventListener('pointercancel', onOrbitPointerCancel, { passive: true, signal: ac.signal });
			} else {
				orbitTouchRoot.addEventListener('touchstart', onOrbitTouchStart, { passive: true, signal: ac.signal });
			}
		}
	}

	function renderPageSubmenuOrbit(opts = {}) {
		const bodyNavElm = document.getElementById('body-nav');
		const submenuElm = document.getElementById('orbit-submenu-pages');
		if (!bodyNavElm || !submenuElm) {
			return;
		}
		const currentLayer = window.pageMenuStack[window.pageMenuStack.length - 1];
		if (!currentLayer) {
			submenuElm.querySelectorAll('.btn-page-orbit').forEach((btn) => {
				btn.classList.add('btn-orbit--phantom');
				btn.innerHTML = '';
				btn.setAttribute('aria-hidden', 'true');
				btn.style.pointerEvents = 'none';
				btn.onclick = null;
			});
			return;
		}
		if (opts.submenuEnter) {
			submenuElm.style.opacity = '0';
		}

		const isOrbitLayer = (currentLayer.parentSlug || '') === 'by-topic';
		let children = getPageChildrenById(currentLayer.parentId);
		if (isOrbitLayer) {
			children = getOrbitTopicTerms();
		}
		const orbitScrollMode = children.length > ORBIT_VISIBLE_COUNT;
		const orbitRubberUi = children.length > 0;
		if (orbitRubberUi) {
			if (orbitScrollMode) {
				const parentKey = `${Number(currentLayer.parentId)}:${currentLayer.parentSlug || ''}`;
				if (window.orbitScrollBoundParentKey !== parentKey) {
					resetOrbitMotion();
					window.orbitScrollBoundParentKey = parentKey;
				}
			} else {
				window.orbitScrollBoundParentKey = '';
			}
			const orbitSnapped = Math.abs(window.orbitVisualAngle - window.orbitStepIndex * ORBIT_SNAP_DEG) < 0.01
				&& Math.abs(getOrbitRubberDegForDisplay()) < 0.035;
			const useFreeMotion = window.orbitWheelGestureActive || isOrbitRubberActive()
				|| isOrbitMomentumActive() || !orbitSnapped;
			syncOrbitVisualState(
				useFreeMotion ? 'free' : (window.orbitVisualAngle !== 0 ? 'step' : 'idle')
			);
		} else {
			window.orbitScrollBoundParentKey = '';
			submenuElm.setAttribute('data-motion', 'idle');
			submenuElm.style.setProperty('--orbit-start', '0deg');
		}
		const buttons = Array.from(submenuElm.querySelectorAll('.btn-page-orbit'));
		if (orbitScrollMode && (!Array.isArray(window.orbitSlotItemIdx) || window.orbitSlotItemIdx.length !== 8)) {
			initByTopicSlotTopicsFromTerms();
		}
		buttons.forEach((btn, slot) => {
			const oi = ORBIT_SLOT_ORDER[slot] ?? slot;
			btn.style.setProperty('--orbit-i', String(oi));
			let itemIndex;
			if (orbitScrollMode) {
				itemIndex = window.orbitSlotItemIdx[slot];
			} else {
				itemIndex = slot;
			}
			const node = (itemIndex >= 0 && itemIndex < children.length) ? children[itemIndex] : null;
			if (!node) {
				btn.classList.remove('btn-orbit--label-suppressed');
				btn.classList.add('btn-orbit--phantom');
				btn.innerHTML = '';
				btn.setAttribute('data-node-key', '');
				btn.setAttribute('aria-hidden', 'true');
				btn.style.pointerEvents = 'none';
				btn.onclick = null;
				return;
			}
			const topicDrilldown = (node.slug || '') === 'by-topic' && getOrbitTopicTerms().length > 0;
			const hasChildren = topicDrilldown || (Array.isArray(node.children) && node.children.length > 0);
			btn.classList.remove('btn-orbit--phantom');
			btn.classList.remove('btn-orbit--label-suppressed');
			btn.style.pointerEvents = 'auto';
			btn.setAttribute('aria-hidden', 'false');
			btn.removeAttribute('data-debug-slot');
			btn.removeAttribute('data-debug-index');
			btn.removeAttribute('data-debug-key');
			setOrbitButtonLabel(btn, node.title || '');
			btn.onclick = (event) => {
				event.preventDefault();
				if (hasChildren) {
					window.pageMenuStack.push({
						mainId: currentLayer.mainId,
						mainLabel: currentLayer.mainLabel,
						parentId: Number(node.id),
						parentSlug: node.slug || '',
						parentLabel: node.title || '',
					});
					renderPageSubmenuOrbit({ submenuEnter: true });
					return;
				}
				closeFullNav();
				window.location.href = node.url || '#';
			};
		});

		bodyNavElm.setAttribute('data-selected', 'page-submenu');
		/* Re-read stack top: after a drill, this render's `currentLayer` const can be stale vs outer call — titles must use latest layer. */
		const topLayer = window.pageMenuStack[window.pageMenuStack.length - 1];
		if (topLayer) {
			const depth = window.pageMenuStack.length;
			let slabLabel = topLayer.parentLabel || '';
			let sansLabel = '';
			if (depth > 1) {
				if (depth === 2) {
					sansLabel = topLayer.mainLabel || '';
				} else {
					const prevLayer = window.pageMenuStack[depth - 2];
					sansLabel = prevLayer && prevLayer.parentLabel ? String(prevLayer.parentLabel) : '';
				}
			}
			if (sansLabel && sansLabel === slabLabel) {
				sansLabel = '';
			}
			/* First drill onward (depth ≥ 2): slab ↔ sans (root/ancestry on slab, ring parent on sans). */
			if (depth >= 2) {
				const t = slabLabel;
				slabLabel = sansLabel;
				sansLabel = t;
			}
			setCenterSectionTitle(slabLabel, sansLabel);
		}
		setActiveMainButton(topLayer ? topLayer.mainId : currentLayer.mainId);
		if (opts.submenuEnter) {
			triggerOrbitSubmenuPagesEnter(submenuElm);
			if (opts.orbitTitleEnterFromHub && typeof window.matchMedia === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				triggerOrbitSectionTitleEnterFromMain();
			}
		}
	}

	function removeNavOrbitShellEnterClass() {
		const shell = document.getElementById('nav-orbit-shell');
		if (shell) {
			shell.classList.remove('nav-orbit-shell--enter');
		}
	}

	/** Visible fade + slide when opening a submenu (data-hide-site-logo). */
	function triggerMenuToggleSubmenuReveal() {
		if (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}
		const mt = document.getElementById('nav-orbit-menu-toggle');
		if (!mt) {
			return;
		}
		mt.classList.remove('nav-orbit__menu-toggle--submenu-reveal');
		void mt.offsetWidth;
		mt.classList.add('nav-orbit__menu-toggle--submenu-reveal');
		const onEnd = (e) => {
			const name = typeof e.animationName === 'string' ? e.animationName : '';
			if (e.target !== mt || !/nav-orbit-menu-toggle-submenu-reveal/i.test(name)) {
				return;
			}
			mt.removeEventListener('animationend', onEnd);
			mt.classList.remove('nav-orbit__menu-toggle--submenu-reveal');
		};
		mt.addEventListener('animationend', onEnd);
		window.setTimeout(() => {
			mt.classList.remove('nav-orbit__menu-toggle--submenu-reveal');
			mt.removeEventListener('animationend', onEnd);
		}, 500);
	}

	function startMenuToggleExitAnimation() {
		if (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}
		const mt = document.getElementById('nav-orbit-menu-toggle');
		if (!mt) {
			return;
		}
		mt.classList.remove('nav-orbit__menu-toggle--exit', 'nav-orbit__menu-toggle--submenu-reveal');
		void mt.offsetWidth;
		mt.classList.add('nav-orbit__menu-toggle--exit');
	}

	function hideSiteLogo() {
		const bodyNavElm = document.getElementById('body-nav');
		const img = document.querySelector('.body-header .logo img');
		const reduceMotion = typeof window.matchMedia === 'function'
			&& window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (img && bodyNavElm && bodyNavElm.getAttribute('data-menu-open') === '1'
			&& bodyNavElm.getAttribute('data-hide-site-logo') !== '1') {
			const cs = getComputedStyle(img);
			window.__eluminateOrbitReturnLogoSnap = {
				transform: cs.transform && cs.transform !== 'none' ? cs.transform : 'none',
				opacity: cs.opacity,
			};
		}
		if (img) {
			const t = getComputedStyle(img).transform;
			img.style.removeProperty('transform');
			if (t && t !== 'none') {
				img.style.setProperty('transform', t, 'important');
			}
			img.style.removeProperty('opacity');
			img.style.removeProperty('animation');
			img.style.removeProperty('animation-timeline');
		}
		if (!bodyNavElm) {
			return;
		}
		const firstTimeHidingLogo = bodyNavElm.getAttribute('data-hide-site-logo') !== '1';
		if (reduceMotion || !img) {
			bodyNavElm.setAttribute('data-hide-site-logo', '1');
			if (firstTimeHidingLogo) {
				removeNavOrbitShellEnterClass();
			}
			return;
		}
		img.classList.add('logo-graphic--fade-out');
		void img.offsetWidth;
		bodyNavElm.setAttribute('data-hide-site-logo', '1');
		if (firstTimeHidingLogo) {
			removeNavOrbitShellEnterClass();
			triggerMenuToggleSubmenuReveal();
		}
		const fadeDone = () => {
			img.removeEventListener('transitionend', onFadeEnd);
			window.clearTimeout(fadeFallback);
			img.classList.remove('logo-graphic--fade-out');
			delete img._orbitHideLogoFade;
		};
		const onFadeEnd = (e) => {
			if (e.target === img && e.propertyName === 'opacity') {
				fadeDone();
			}
		};
		img.addEventListener('transitionend', onFadeEnd);
		const fadeFallback = window.setTimeout(fadeDone, 400);
		img._orbitHideLogoFade = { onFadeEnd, fadeFallback };
	}

	function revealSiteLogo() {
		const bodyNavElm = document.getElementById('body-nav');
		const img = document.querySelector('.body-header .logo img');
		if (!bodyNavElm) {
			return;
		}
		const wasHidden = bodyNavElm.getAttribute('data-hide-site-logo') === '1';
		if (!wasHidden) {
			bodyNavElm.removeAttribute('data-hide-site-logo');
			if (img) {
				img.classList.remove('logo-reveal-in');
				img.style.removeProperty('transform');
				img.style.removeProperty('animation');
				img.style.removeProperty('opacity');
			}
			syncLogoGraphicForNavState();
			return;
		}
		if (!img) {
			bodyNavElm.removeAttribute('data-hide-site-logo');
			return;
		}
		void img.offsetWidth;
		requestAnimationFrame(() => {
			requestAnimationFrame(() => {
				img.classList.add('logo-reveal-in');
				const cleanup = () => {
					img.removeEventListener('animationend', onAnimEnd);
					clearTimeout(fallback);
					delete img._orbitLogoRevealAnim;
					img.style.animation = 'none';
					img.style.opacity = '1';
					img.classList.remove('logo-reveal-in');
					img.style.removeProperty('top');
					img.style.removeProperty('transform');
					bodyNavElm.removeAttribute('data-hide-site-logo');
					const menuToggle = document.getElementById('nav-orbit-menu-toggle');
					if (menuToggle) {
						menuToggle.classList.remove('nav-orbit__menu-toggle--exit', 'nav-orbit__menu-toggle--submenu-reveal');
					}
					requestAnimationFrame(() => {
						/* Do not remove animation/opacity here — that strips syncLogoGraphicForNavState()'s !important freeze
						 * (earlier this ran before sync and broke scale). Only sync. */
						syncLogoGraphicForNavState();
					});
				};
				const onAnimEnd = (e) => {
					const name = typeof e.animationName === 'string' ? e.animationName : '';
					if (e.target === img && /logo-graphic-reveal-enter/i.test(name)) {
						cleanup();
					}
				};
				img.addEventListener('animationend', onAnimEnd);
				const fallback = setTimeout(cleanup, 520);
				img._orbitLogoRevealAnim = { onAnimEnd, fallback };
			});
		});
	}

	function returnToMainMenu() {
		const bodyNavElm = document.getElementById('body-nav');
		if (!bodyNavElm) {
			return;
		}
		if (bodyNavElm.getAttribute('data-hide-site-logo') === '1') {
			startMenuToggleExitAnimation();
		}
		window.pageMenuStack = [];
		resetOrbitMotion();
		bodyNavElm.setAttribute('data-selected', '');
		document.querySelectorAll('#orbit-hub .btn-main-menu').forEach((elm) => elm.classList.remove('active'));
		setCenterSectionTitle('', '');
		revealSiteLogo();
		renderMainHubOrbit();
	}

	function closeSubPanel() {
		const bodyNavElm = document.getElementById('body-nav');
		if (!bodyNavElm) {
			return;
		}
		if (bodyNavElm.getAttribute('data-selected') === 'page-submenu') {
			if (window.pageMenuStack.length > 1) {
				window.pageMenuStack.pop();
				renderPageSubmenuOrbit({ submenuEnter: true });
				return;
			}
			returnToMainMenu();
			return;
		}
		returnToMainMenu();
	}

	function closeFullNav() {
		const bodyNavElm = document.getElementById('body-nav');
		const logoElm = document.querySelector('.body-header .logo');
		const orbitHubElm = document.getElementById('orbit-hub');
		const navOrbitShell = document.getElementById('nav-orbit-shell');
		if (navOrbitShell) {
			navOrbitShell.classList.remove('nav-orbit-shell--enter');
		}
		const navOrbitMenuToggle = document.getElementById('nav-orbit-menu-toggle');
		if (navOrbitMenuToggle) {
			navOrbitMenuToggle.classList.remove('nav-orbit__menu-toggle--submenu-reveal', 'nav-orbit__menu-toggle--exit');
		}
		if (orbitHubElm) {
			orbitHubElm.classList.remove('orbit-hub--enter');
		}
		const orbitSubmenuPagesElm = document.getElementById('orbit-submenu-pages');
		if (orbitSubmenuPagesElm) {
			orbitSubmenuPagesElm.classList.remove('orbit-submenu-pages--enter');
			orbitSubmenuPagesElm.style.removeProperty('opacity');
		}
		const orbitSectionTitleElm = document.getElementById('orbit-section-title');
		if (orbitSectionTitleElm) {
			orbitSectionTitleElm.classList.remove('orbit-section-title--enter');
			orbitSectionTitleElm.style.removeProperty('opacity');
		}
		if (bodyNavElm) {
			bodyNavElm.setAttribute('data-selected', '');
			bodyNavElm.setAttribute('data-menu-open', '');
			document.querySelectorAll('#orbit-hub .btn-main-menu').forEach((elm) => elm.classList.remove('active'));
			setCenterSectionTitle('', '');
			bodyNavElm.removeAttribute('data-hide-site-logo');
			const logoImg = document.querySelector('.body-header .logo img');
			if (logoImg) {
				const fd = logoImg._orbitHideLogoFade;
				if (fd) {
					logoImg.removeEventListener('transitionend', fd.onFadeEnd);
					window.clearTimeout(fd.fadeFallback);
					delete logoImg._orbitHideLogoFade;
				}
				const ra = logoImg._orbitLogoRevealAnim;
				if (ra) {
					logoImg.removeEventListener('animationend', ra.onAnimEnd);
					window.clearTimeout(ra.fallback);
					delete logoImg._orbitLogoRevealAnim;
				}
				logoImg.classList.remove('logo-reveal-in', 'logo-graphic--fade-out');
				logoImg.style.removeProperty('transform');
				logoImg.style.removeProperty('animation');
				logoImg.style.removeProperty('animation-timeline');
				logoImg.style.removeProperty('opacity');
				logoImg.style.removeProperty('top');
			}
			if (window.__eluminateOrbitReturnLogoSnap) {
				delete window.__eluminateOrbitReturnLogoSnap;
			}
			syncLogoGraphicForNavState();
		}
		window.pageMenuStack = [];
		resetOrbitMotion();
		if (logoElm) {
			clearLogoMenuFlare(logoElm);
			logoElm.classList.remove('is-expanded');
		}
	}

	/**
	 * Supports both main menu patterns:
	 * - submenu trigger (id provided)
	 * - direct link (url provided, no submenu id)
	 */
	function activateMainMenuItem(triggerElm, id, url) {
		const pageId = Number((triggerElm && triggerElm.getAttribute('data-page-id')) || id);
		const targetUrl = typeof url === 'string' && url !== '' ? url : (triggerElm && triggerElm.getAttribute('data-target-url')) || '';
		const mode = (triggerElm && triggerElm.getAttribute('data-nav-mode')) || (targetUrl ? 'link' : 'submenu');
		const pageLabel = (triggerElm && triggerElm.getAttribute('data-page-label')) || '';
		const pageSlug = (triggerElm && triggerElm.getAttribute('data-page-slug')) || '';
		const bodyNavElm = document.getElementById('body-nav');

		if (mode === 'link' && targetUrl) {
			closeFullNav();
			window.location.href = targetUrl;
			return false;
		}
		if (!bodyNavElm || !Number.isInteger(pageId)) {
			return false;
		}
		const children = getPageChildrenById(pageId);
		if (!children.length && targetUrl) {
			closeFullNav();
			window.location.href = targetUrl;
			return false;
		}
		const titleElm = document.getElementById('orbit-section-title');
		const titleEnter = typeof window.matchMedia === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (titleElm && titleEnter) {
			titleElm.style.setProperty('opacity', '0');
		}
		bodyNavElm.setAttribute('data-menu-open', '1');
		hideSiteLogo();
		window.pageMenuStack = [
			{
				mainId: pageId,
				mainLabel: pageLabel || (triggerElm ? triggerElm.textContent.trim() : ''),
				parentId: pageId,
				parentSlug: pageSlug,
				parentLabel: pageLabel || (triggerElm ? triggerElm.textContent.trim() : ''),
			},
		];
		bodyNavElm.setAttribute('data-selected', 'page-submenu');
		resetOrbitMotion();
		renderPageSubmenuOrbit({ submenuEnter: true, orbitTitleEnterFromHub: true });
		return false;
	}

	function startNavOrbitShellControlsEnter() {
		const reduceMotion = typeof window.matchMedia === 'function'
			&& window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (reduceMotion) {
			return;
		}
		const shell = document.getElementById('nav-orbit-shell');
		if (!shell) {
			return;
		}
		shell.hidden = false;
		shell.classList.remove('nav-orbit-shell--enter');
		void shell.offsetWidth;
		shell.classList.add('nav-orbit-shell--enter');
		const home = shell.querySelector('.nav-orbit__home');
		const closeBtn = shell.querySelector('.nav-orbit__close');
		const menuToggle = document.getElementById('nav-orbit-menu-toggle');
		const animNameRe = /nav-orbit-shell-(control-enter|menu-toggle-enter)/i;
		let pending = (home ? 1 : 0) + (closeBtn ? 1 : 0) + (menuToggle ? 1 : 0);
		const finish = () => {
			shell.classList.remove('nav-orbit-shell--enter');
		};
		const onEnd = (e) => {
			if (!animNameRe.test(e.animationName || '')) {
				return;
			}
			e.currentTarget.removeEventListener('animationend', onEnd);
			pending--;
			if (pending <= 0) {
				finish();
			}
		};
		if (pending === 0) {
			finish();
			return;
		}
		if (home) {
			home.addEventListener('animationend', onEnd);
		}
		if (closeBtn) {
			closeBtn.addEventListener('animationend', onEnd);
		}
		if (menuToggle) {
			menuToggle.addEventListener('animationend', onEnd);
		}
		window.setTimeout(() => {
			if (shell.classList.contains('nav-orbit-shell--enter')) {
				finish();
			}
			if (home) {
				home.removeEventListener('animationend', onEnd);
			}
			if (closeBtn) {
				closeBtn.removeEventListener('animationend', onEnd);
			}
			if (menuToggle) {
				menuToggle.removeEventListener('animationend', onEnd);
			}
		}, 520);
	}

	function clearLogoMenuFlare(logo) {
		if (!logo) {
			return;
		}
		const t = logo._eluminateFlareTimer;
		if (typeof t === 'number') {
			window.clearTimeout(t);
		}
		delete logo._eluminateFlareTimer;
		logo.classList.remove('eluminate-menu-flare');
	}

	/** One-shot solar flare (layout.css); JS restarts animation every open — CSS-only selectors were unreliable. */
	function triggerLogoMenuFlare(logo) {
		if (!logo) {
			return;
		}
		const reduceMotion =
			typeof window.matchMedia === 'function' &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (reduceMotion) {
			return;
		}
		clearLogoMenuFlare(logo);
		void logo.offsetWidth; // force reflow so removing + re-adding the class restarts the animation
		logo.classList.add('eluminate-menu-flare');
		logo._eluminateFlareTimer = window.setTimeout(function () {
			logo.classList.remove('eluminate-menu-flare');
			delete logo._eluminateFlareTimer;
		}, 850);
	}

	function syncContentMenuTrigger(open) {
		const trigger = document.getElementById('content-menu-trigger');
		if (!trigger) {
			return;
		}
		trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
		trigger.setAttribute(
			'aria-label',
			open
				? <?php echo wp_json_encode( __( 'Close menu', 'eluminate-standalone' ) ); ?>
				: <?php echo wp_json_encode( __( 'Open menu', 'eluminate-standalone' ) ); ?>
		);
	}

	function toggleLogoMenu() {
		const nav = document.getElementById('body-nav');
		const logo = document.querySelector('.body-header .logo');
		if (!nav || !logo) {
			return;
		}
		const wasOpen = nav.getAttribute('data-menu-open') === '1';
		if (!wasOpen) {
			if (typeof window.eluminatePrepareMenuOpen === 'function') {
				window.eluminatePrepareMenuOpen();
			}
			logo.classList.add('is-expanded');
			triggerLogoMenuFlare(logo);
			const img = document.querySelector('.body-header .logo img');
			const reduceMotion = typeof window.matchMedia === 'function'
				&& window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			let logoPrefreezeSnap = null;
			if (img && !reduceMotion) {
				const cs = getComputedStyle(img);
				logoPrefreezeSnap = {
					transform: cs.transform && cs.transform !== 'none' ? cs.transform : 'none',
					opacity: cs.opacity,
				};
			}
			nav.setAttribute('data-menu-open', '1');
			const runOpenUi = function () {
				startNavOrbitShellControlsEnter();
				const hub = document.getElementById('orbit-hub');
				if (hub && !reduceMotion) {
					hub.classList.remove('orbit-hub--enter');
					void hub.offsetWidth;
					hub.classList.add('orbit-hub--enter');
					hub.addEventListener('animationend', function onOrbitHubEnterEnd(e) {
						if (e.target !== hub || e.animationName !== 'orbit-hub-menu-enter') {
							return;
						}
						hub.classList.remove('orbit-hub--enter');
						hub.removeEventListener('animationend', onOrbitHubEnterEnd);
					});
				}
				syncLogoGraphicForNavState(logoPrefreezeSnap);
				renderMainHubOrbit();
			};
			if (typeof window.eluminateAfterMenuChromeReady === 'function') {
				window.eluminateAfterMenuChromeReady(runOpenUi);
			} else {
				runOpenUi();
			}
		} else {
			closeFullNav();
		}
	}

	/**
	 * Detach scroll-linked view() animation while the main orbit is open. Snapshot computed transform/opacity
	 * first so the logo does not jump to scale(1) (larger than the active keyframe on most pages).
	 *
	 * @param {{ transform: string, opacity: string } | undefined} prefreezeSnap Read BEFORE setting data-menu-open:
	 * layout.css applies animation:none !important on the img once the menu is open, which wipes scroll-driven
	 * transform in getComputedStyle if sampled too late.
	 */
	/**
	 * Scale/fade the site logo from document scrollY (progress starts at the first scroll pixel).
	 * Matches styles/layout.css @keyframes scale-on-scroll; view()/exit timelines wait until the mark leaves
	 * the scrollport, which felt late.
	 */
	function updateLogoScrollScale() {
		const img = document.querySelector('.body-header .logo img');
		if (!img) {
			return;
		}
		const nav = document.getElementById('body-nav');
		const menuOpen = nav && nav.getAttribute('data-menu-open') === '1';
		const hideGraphic = nav && nav.getAttribute('data-hide-site-logo') === '1';
		if (menuOpen) {
			return;
		}
		if (
			typeof window.matchMedia === 'function' &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches
		) {
			img.style.removeProperty('--eluminate-logo-scroll-scale');
			img.style.removeProperty('--eluminate-logo-scroll-opacity');
			return;
		}
		if (hideGraphic) {
			return;
		}

		const rangePx = 400;
		const y = window.scrollY || document.documentElement.scrollTop || 0;
		const t = Math.min(1, Math.max(0, y / rangePx));

		let scale;
		let opacity;
		if (t <= 0.55) {
			const u = t / 0.55;
			scale = 1 + (0.7 - 1) * u;
			opacity = 1;
		} else if (t <= 0.75) {
			const u = (t - 0.55) / 0.2;
			scale = 0.7 + (0.55 - 0.7) * u;
			opacity = 1 + (0.5 - 1) * u;
		} else {
			const u = (t - 0.75) / 0.25;
			scale = 0.55 + (0.4 - 0.55) * u;
			opacity = 0.5 + (0 - 0.5) * u;
		}

		img.style.setProperty('--eluminate-logo-scroll-scale', String(scale));
		img.style.setProperty('--eluminate-logo-scroll-opacity', String(opacity));
	}

	function syncLogoGraphicForNavState(prefreezeSnap) {
		const nav = document.getElementById('body-nav');
		const img = document.querySelector('.body-header .logo img');
		if (!nav || !img) {
			return;
		}
		const menuOpen = nav.getAttribute('data-menu-open') === '1';
		const hideGraphic = nav.getAttribute('data-hide-site-logo') === '1';
		if (menuOpen && !hideGraphic) {
			let tf;
			let op;
			if (window.__eluminateOrbitReturnLogoSnap) {
				tf = window.__eluminateOrbitReturnLogoSnap.transform;
				op = window.__eluminateOrbitReturnLogoSnap.opacity;
				delete window.__eluminateOrbitReturnLogoSnap;
			} else if (
				prefreezeSnap
				&& typeof prefreezeSnap.transform === 'string'
				&& typeof prefreezeSnap.opacity === 'string'
			) {
				tf = prefreezeSnap.transform && prefreezeSnap.transform !== 'none' ? prefreezeSnap.transform : 'none';
				op = prefreezeSnap.opacity;
			} else {
				const cs = getComputedStyle(img);
				tf = cs.transform && cs.transform !== 'none' ? cs.transform : 'none';
				op = cs.opacity;
			}
			img.style.setProperty('animation', 'none', 'important');
			img.style.setProperty('animation-timeline', 'auto', 'important');
			img.style.setProperty('transform', tf, 'important');
			img.style.setProperty('opacity', op, 'important');
			return;
		}
		/* Submenu: graphic hidden — do not strip animation/opacity off the logo img. Doing so lets the scroll-driven
		 * view() animation run again before revealSiteLogo clears data-hide-site-logo, so computed scale jumps “up” and
		 * the menu-button return path looks wrong even when stash exists. */
		if (menuOpen && hideGraphic) {
			return;
		}
		/* Do not delete return stash while menu is still open but graphic hidden (submenu); returnToMainMenu clears
		 * data-selected before revealSiteLogo removes data-hide-site-logo — one observer tick would wipe the stash. */
		if (!menuOpen && window.__eluminateOrbitReturnLogoSnap) {
			delete window.__eluminateOrbitReturnLogoSnap;
		}
		img.style.removeProperty('animation');
		img.style.removeProperty('animation-timeline');
		img.style.removeProperty('opacity');
		if (!hideGraphic) {
			img.style.removeProperty('transform');
		}
		updateLogoScrollScale();
	}

	(function initLogoScrollScale() {
		let ticking = false;
		const onScrollOrResize = function () {
			if (ticking) {
				return;
			}
			ticking = true;
			window.requestAnimationFrame(function () {
				ticking = false;
				updateLogoScrollScale();
			});
		};
		window.addEventListener('scroll', onScrollOrResize, { passive: true });
		window.addEventListener('resize', onScrollOrResize, { passive: true });
		updateLogoScrollScale();
	})();
</script>

<div class="body-nav" id="body-nav" data-selected="" data-menu-open="">
	<div id="backdrop" onclick="closeFullNav()"></div>
</div>
<div class="nav-orbit-shell" id="nav-orbit-shell" hidden>
	<a class="nav-orbit__home" href="<?php echo esc_url( home_url( '/' ) ); ?>" onclick="closeFullNav()" aria-label="<?php echo esc_attr__( 'Go to homepage', 'eluminate-standalone' ); ?>">
		<img src="<?php echo esc_url( $home_icon ); ?>" width="32" height="32" alt="" decoding="async" />
		<span class="nav-orbit__home-label"><?php echo esc_html__( 'Home', 'eluminate-standalone' ); ?></span>
	</a>
	<button type="button" class="nav-orbit__menu-toggle" id="nav-orbit-menu-toggle" onclick="returnToMainMenu()" aria-label="<?php echo esc_attr__( 'Back to main menu', 'eluminate-standalone' ); ?>">
		<svg class="nav-orbit__menu-icon" xmlns="http://www.w3.org/2000/svg" viewBox="-1 0 44 42" aria-hidden="true" focusable="false">
			<g class="orbit-dots">
				<circle cx="4.2" cy="21" r="4.2" />
				<circle cx="37.8" cy="21" r="4.2" />
				<circle cx="12.6" cy="35.5" r="4.2" />
				<circle cx="29.4" cy="6.5" r="4.2" />
				<circle cx="29.4" cy="35.5" r="4.2" />
				<circle cx="12.6" cy="6.5" r="4.2" />
			</g>
			<path d="M16.3 25.2c-2.3 0-4.2-1.9-4.2-4.2s1.9-4.2 4.2-4.2h9.4c2.3 0 4.2 1.9 4.2 4.2s-1.9 4.2-4.2 4.2z" />
		</svg>
		<span class="nav-orbit__menu-label"><?php echo esc_html__( 'menu', 'eluminate-standalone' ); ?></span>
	</button>
	<button type="button" class="nav-orbit__close" onclick="closeFullNav()" aria-label="<?php echo esc_attr__( 'Close menu', 'eluminate-standalone' ); ?>">
		<img src="<?php echo esc_url( $close_icon ); ?>" width="30" height="30" alt="" decoding="async" />
		<span class="nav-orbit__close-label"><?php echo esc_html__( 'close', 'eluminate-standalone' ); ?></span>
	</button>
</div>
<script>
	(function initMenuViewportAndScrollLock() {
		const root = document.documentElement;
		let lockedScrollY = 0;
		let menuScrollLocked = false;
		let vvListenersBound = false;

		function isMenuUiOpen() {
			const nav = document.getElementById('body-nav');
			if (!nav) {
				return false;
			}
			if (nav.getAttribute('data-menu-open') === '1') {
				return true;
			}
			const selected = nav.getAttribute('data-selected') || '';
			return selected !== '';
		}

		function syncMenuVisualViewport() {
			const vv = window.visualViewport;
			if (!vv) {
				root.style.removeProperty('--menu-vv-height');
				root.style.removeProperty('--menu-vv-offset-top');
				return;
			}
			root.style.setProperty('--menu-vv-height', `${Math.round(vv.height)}px`);
			root.style.setProperty('--menu-vv-offset-top', `${Math.round(vv.offsetTop)}px`);
		}

		function bindVisualViewportListeners() {
			if (vvListenersBound || !window.visualViewport) {
				return;
			}
			vvListenersBound = true;
			const onVvChange = function () {
				if (!isMenuUiOpen()) {
					return;
				}
				syncMenuVisualViewport();
			};
			window.visualViewport.addEventListener('resize', onVvChange);
			window.visualViewport.addEventListener('scroll', onVvChange);
		}

		function lockMenuScroll() {
			lockedScrollY = window.scrollY || root.scrollTop || 0;
			syncMenuVisualViewport();
			bindVisualViewportListeners();
			root.classList.add('eluminate-menu-scroll-lock');
			document.body.style.width = '100%';
			document.body.style.top = `-${lockedScrollY}px`;
			document.body.style.position = 'fixed';
			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(syncMenuVisualViewport);
			});
		}

		function unlockMenuScroll() {
			root.classList.remove('eluminate-menu-scroll-lock');
			document.body.style.removeProperty('position');
			document.body.style.removeProperty('top');
			document.body.style.removeProperty('width');
			root.style.removeProperty('--menu-vv-height');
			root.style.removeProperty('--menu-vv-offset-top');
			window.scrollTo(0, lockedScrollY);
			lockedScrollY = 0;
		}

		function onMenuOpenChange(open) {
			if (open && !menuScrollLocked) {
				menuScrollLocked = true;
				lockMenuScroll();
				return;
			}
			if (!open && menuScrollLocked) {
				menuScrollLocked = false;
				unlockMenuScroll();
				return;
			}
			if (open) {
				syncMenuVisualViewport();
			}
		}

		window.eluminatePrepareMenuOpen = function () {
			syncMenuVisualViewport();
			if (!menuScrollLocked) {
				menuScrollLocked = true;
				lockMenuScroll();
			}
		};
		window.eluminateSyncMenuVisualViewport = syncMenuVisualViewport;
		window.eluminateAfterMenuChromeReady = function (callback) {
			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(function () {
					syncMenuVisualViewport();
					if (typeof callback === 'function') {
						callback();
					}
				});
			});
		};

		const nav = document.getElementById('body-nav');
		if (nav) {
			const observer = new MutationObserver(function () {
				onMenuOpenChange(isMenuUiOpen());
			});
			observer.observe(nav, {
				attributes: true,
				attributeFilter: ['data-menu-open', 'data-selected'],
			});
			if (isMenuUiOpen()) {
				onMenuOpenChange(true);
			}
		}

		window.addEventListener('orientationchange', function () {
			if (!isMenuUiOpen()) {
				return;
			}
			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(syncMenuVisualViewport);
			});
		});
	})();
</script>
<script>
	(function () {
		const nav = document.getElementById('body-nav');
		const shell = document.getElementById('nav-orbit-shell');
		const menuToggle = document.getElementById('nav-orbit-menu-toggle');
		if (!nav || !shell || !menuToggle) {
			return;
		}
		initOrbitWheel();
		const syncShell = () => {
			const open = nav.getAttribute('data-menu-open') === '1';
			shell.hidden = !open;
			const logoHidden = nav.getAttribute('data-hide-site-logo') === '1';
			menuToggle.setAttribute('aria-hidden', logoHidden ? 'false' : 'true');
			if (logoHidden) {
				shell.classList.remove('nav-orbit-shell--enter');
			}
			syncContentMenuTrigger(open);
			syncLogoGraphicForNavState();
		};
		syncShell();
		new MutationObserver(syncShell).observe(nav, { attributes: true, attributeFilter: ['data-menu-open', 'data-selected', 'data-hide-site-logo'] });
	})();
</script>
