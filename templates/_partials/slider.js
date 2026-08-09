window.featuredDealsSlider = function () {
    return {
        index: 0,
        maxIndex: 0,
        perPage: 1,
        total: 0,
        scrollTimer: null,

        init() {
            this.$nextTick(() => {
                this.measure();
                this.$refs.track.addEventListener('scroll', () => this.sync(), { passive: true });
            });
        },

        measure() {
            const track = this.$refs.track;
            const firstItem = track.children[0];

            if (!firstItem) {
                return;
            }

            const styles = window.getComputedStyle(track);
            const gap = parseFloat(styles.columnGap || styles.gap) || 0;
            const itemWidth = firstItem.getBoundingClientRect().width + gap;

            this.total = track.children.length;
            this.perPage = Math.max(1, Math.round((track.clientWidth + gap) / itemWidth));
            this.maxIndex = Math.max(0, this.total - this.perPage);
            this.index = Math.min(this.index, this.maxIndex);
            this.scrollToIndex(this.index, 'auto');
        },

        previous() {
            this.goTo(this.index - this.perPage);
        },

        next() {
            this.goTo(this.index + this.perPage);
        },

        goTo(index) {
            this.index = Math.max(0, Math.min(index, this.maxIndex));
            this.scrollToIndex(this.index);
        },

        scrollToIndex(index, behavior = 'smooth') {
            const track = this.$refs.track;
            const item = track.children[index];

            if (!item) {
                return;
            }

            track.scrollTo({
                left: item.offsetLeft - track.offsetLeft,
                behavior,
            });
        },

        sync() {
            window.clearTimeout(this.scrollTimer);
            this.scrollTimer = window.setTimeout(() => {
                const track = this.$refs.track;
                let closestIndex = 0;
                let closestDistance = Infinity;

                Array.from(track.children).forEach((item, itemIndex) => {
                    const distance = Math.abs(track.scrollLeft - (item.offsetLeft - track.offsetLeft));

                    if (distance < closestDistance) {
                        closestIndex = itemIndex;
                        closestDistance = distance;
                    }
                });

                this.index = Math.min(closestIndex, this.maxIndex);
            }, 80);
        },
    };
};
