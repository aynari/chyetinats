{!! view_render_event('bagisto.shop.layout.header.before') !!}

<!-- Announcement Bar -->
<div class="w-full text-center" style="background-color: #f0e4d5;">
    <p class="px-4 py-2.5 text-sm font-bold uppercase text-black" style="letter-spacing: 0.15em;">
        FREE SHIPPING IN BELGRADE
    </p>
</div>

@if (core()->getCurrentChannel()->locales()->count() > 1 || core()->getCurrentChannel()->currencies()->count() > 1)
    <div class="max-lg:hidden">
        <x-shop::layouts.header.desktop.top />
    </div>
@endif

<header class="shadow-gray sticky top-0 z-10 bg-white shadow-sm max-lg:shadow-none">
    <v-header-switcher>
        <!-- Desktop Header Shimmer -->
        <div class="flex flex-wrap max-lg:hidden">
            <div class="grid min-h-[78px] w-full grid-cols-3 items-center px-[60px] max-1180:px-8">
                <div class="flex items-center gap-x-8 max-[1180px]:gap-x-5">
                    <span class="shimmer h-5 w-16 rounded" role="presentation"></span>
                    <span class="shimmer h-5 w-20 rounded" role="presentation"></span>
                    <span class="shimmer h-5 w-12 rounded" role="presentation"></span>
                </div>

                <div class="flex justify-center">
                    <span class="shimmer block h-[29px] w-[180px] rounded" role="presentation"></span>
                </div>

                <div class="flex items-center justify-end gap-x-6">
                    <span class="shimmer h-6 w-6 rounded" role="presentation"></span>
                    <span class="shimmer h-6 w-6 rounded" role="presentation"></span>
                    <span class="shimmer h-6 w-6 rounded" role="presentation"></span>
                </div>
            </div>
        </div>

        <!-- Mobile Header Shimmer -->
        <div class="flex flex-wrap gap-4 px-4 pb-4 pt-6 shadow-sm lg:hidden">
            <div class="flex w-full items-center justify-between">
                <div class="flex items-center gap-x-1.5">
                    <span class="shimmer block h-6 w-6 rounded" role="presentation"></span>
                </div>

                <span class="shimmer block h-[29px] w-[140px] rounded" role="presentation"></span>

                <div class="flex items-center gap-x-4">
                    <span class="shimmer block h-6 w-6 rounded" role="presentation"></span>
                    <span class="shimmer block h-6 w-6 rounded" role="presentation"></span>
                </div>
            </div>
        </div>
    </v-header-switcher>
</header>

{!! view_render_event('bagisto.shop.layout.header.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-header-switcher-template"
    >
        <v-desktop-header v-if="isDesktop"></v-desktop-header>

        <v-mobile-header v-else></v-mobile-header>
    </script>

    <script type="module">
        app.component('v-header-switcher', {
            template: '#v-header-switcher-template',

            data() {
                return {
                    isDesktop: window.innerWidth >= 1024
                }
            },

            mounted() {
                this.media = window.matchMedia('(min-width: 1024px)');

                this.media.addEventListener('change', this.handleMedia);
            },

            beforeUnmount() {
                this.media.removeEventListener('change', this.handleMedia);
            },

            methods: {
                handleMedia(e) {
                    this.isDesktop = e.matches;
                }
            }
        });

        app.component('v-desktop-header', {
            template: '#v-desktop-header-template'
        });

        app.component('v-mobile-header', {
            template: '#v-mobile-header-template'
        });
    </script>

    <script
        type="text/x-template"
        id="v-desktop-header-template"
    >
        <x-shop::layouts.header.desktop />
    </script>

    <script
        type="text/x-template"
        id="v-mobile-header-template"
    >
        <x-shop::layouts.header.mobile />
    </script>
@endPushOnce
