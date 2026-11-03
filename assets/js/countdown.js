/**
 * Library Seat Reservation and Monitoring System
 * Live Session Countdown Timer
 */

class LibraryCountdown {
    /**
     * @param {Object} options
     * @param {number|string} options.targetTimestamp - Unix timestamp in ms or ISO string
     * @param {HTMLElement|string} options.displayElement - Element or ID to show HH:MM:SS
     * @param {Function} [options.onWarning] - Callback when <= 15 minutes left
     * @param {Function} [options.onExpire] - Callback when expired
     * @param {number} [options.warningSeconds=900] - Warning threshold (default 15 mins)
     */
    constructor(options) {
        this.targetTime = typeof options.targetTimestamp === 'number' 
            ? options.targetTimestamp 
            : new Date(options.targetTimestamp).getTime();

        this.element = typeof options.displayElement === 'string'
            ? document.getElementById(options.displayElement)
            : options.displayElement;

        this.onWarning = options.onWarning || null;
        this.onExpire = options.onExpire || null;
        this.warningSeconds = options.warningSeconds || 900;
        this.warningFired = false;
        this.timerId = null;

        if (this.element) {
            this.update();
            this.start();
        }
    }

    start() {
        if (this.timerId) clearInterval(this.timerId);
        this.timerId = setInterval(() => this.update(), 1000);
    }

    stop() {
        if (this.timerId) {
            clearInterval(this.timerId);
            this.timerId = null;
        }
    }

    getRemainingSeconds() {
        const now = Date.now();
        const diff = Math.max(0, Math.floor((this.targetTime - now) / 1000));
        return diff;
    }

    formatTime(totalSeconds) {
        if (totalSeconds <= 0) return "00:00:00";
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        const pad = (num) => String(num).padStart(2, '0');
        return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    }

    update() {
        const remaining = this.getRemainingSeconds();

        if (this.element) {
            this.element.textContent = this.formatTime(remaining);
        }

        // Check warning threshold (<= 15 minutes remaining)
        if (remaining <= this.warningSeconds && remaining > 0 && !this.warningFired) {
            this.warningFired = true;
            if (typeof this.onWarning === 'function') {
                this.onWarning(remaining);
            }
        }

        // Check expiration
        if (remaining <= 0) {
            this.stop();
            if (this.element) {
                this.element.textContent = "00:00:00";
            }
            if (typeof this.onExpire === 'function') {
                this.onExpire();
            }
        }
    }
}

/**
 * Initialize all countdown elements on the page with data-countdown-end attribute
 */
function initDynamicCountdowns() {
    const countdownElements = document.querySelectorAll('[data-countdown-end]');
    countdownElements.forEach(el => {
        const endTime = el.getAttribute('data-countdown-end');
        if (!endTime) return;

        new LibraryCountdown({
            targetTimestamp: endTime,
            displayElement: el,
            onWarning: () => {
                el.classList.add('warning');
            },
            onExpire: () => {
                el.classList.add('expired');
                el.textContent = "Expired";
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initDynamicCountdowns);

