@php
    $showWishlist = (bool) core()->getConfigData('customer.settings.wishlist.wishlist_option');

    $shopCategories = app(\Webkul\Category\Repositories\CategoryRepository::class)
        ->getVisibleCategoryTree(core()->getCurrentChannel()->root_category_id);
@endphp

<v-mobile-nav></v-mobile-nav>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-mobile-nav-template"
    >
        <div class="relative">
            <div class="flex items-center justify-between gap-4 px-4 pb-4 pt-6 shadow-sm lg:hidden">
                <div class="flex items-center">
                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.drawer.before') !!}

                    <v-mobile-drawer></v-mobile-drawer>

                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.drawer.after') !!}
                </div>

                <div class="flex flex-1 justify-center">
                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.logo.before') !!}

                    <a
                        href="{{ route('shop.home.index') }}"
                        class="flex items-center"
                        aria-label="@lang('shop::app.components.layouts.header.mobile.bagisto')"
                    >
                        @if (core()->getCurrentChannel()->logo_url)
                            <img
                                src="{{ core()->getCurrentChannel()->logo_url }}"
                                alt="{{ config('app.name') }}"
                                class="h-7 w-auto"
                            >
                        @else
                            <span class="text-xl font-extrabold text-black" style="letter-spacing: 0.15em;">
                                {{ strtoupper(config('app.name', 'CHAYETINATS')) }}
                            </span>
                        @endif
                    </a>

                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.logo.after') !!}
                </div>

                <div class="flex items-center gap-x-4">
                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.profile.before') !!}

                    @guest('customer')
                        <a
                            href="{{ route('shop.customer.session.create') }}"
                            aria-label="@lang('shop::app.components.layouts.header.mobile.account')"
                        >
                            <span class="icon-users cursor-pointer text-2xl"></span>
                        </a>
                    @endguest

                    @auth('customer')
                        <a
                            href="{{ route('shop.customers.account.index') }}"
                            aria-label="@lang('shop::app.components.layouts.header.mobile.account')"
                        >
                            <span class="icon-users cursor-pointer text-2xl"></span>
                        </a>
                    @endauth

                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.profile.after') !!}

                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.search.before') !!}

                    <button
                        type="button"
                        class="icon-search cursor-pointer text-2xl"
                        aria-label="@lang('shop::app.components.layouts.header.mobile.search')"
                        @click="toggleSearch"
                    ></button>

                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.search.after') !!}

                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.mini_cart.before') !!}

                    @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                        @include('shop::checkout.cart.mini-cart')
                    @endif

                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.mini_cart.after') !!}
                </div>
            </div>

            <div
                v-show="searchOpen"
                class="border-b border-zinc-200 bg-white px-4 pb-4 lg:hidden"
            >
                <form
                    action="{{ route('shop.search.index') }}"
                    class="flex items-center gap-2"
                    role="search"
                >
                    <div class="icon-search pointer-events-none flex items-center text-2xl text-zinc-500"></div>

                    <input
                        ref="searchInput"
                        type="text"
                        class="block w-full bg-transparent py-2 text-sm font-medium text-gray-900 outline-none"
                        name="query"
                        value="{{ request('query') }}"
                        placeholder="@lang('shop::app.components.layouts.header.mobile.search-text')"
                        required
                    >

                    <button
                        type="button"
                        class="icon-cancel cursor-pointer text-2xl text-zinc-500"
                        aria-label="@lang('shop::app.components.layouts.header.mobile.search')"
                        @click="toggleSearch"
                    ></button>
                </form>
            </div>
        </div>
    </script>

    <script
        type="text/x-template"
        id="v-mobile-drawer-template"
    >
        <x-shop::drawer
            position="left"
            width="100%"
            @close="onDrawerClose"
        >
            <x-slot:toggle>
                <span class="icon-hamburger cursor-pointer text-2xl"></span>
            </x-slot>

            <x-slot:header>
                <div class="flex items-center justify-between">
                    <a href="{{ route('shop.home.index') }}">
                        @if (core()->getCurrentChannel()->logo_url)
                            <img
                                src="{{ core()->getCurrentChannel()->logo_url }}"
                                alt="{{ config('app.name') }}"
                                class="h-7 w-auto"
                            >
                        @else
                            <span class="text-xl font-extrabold text-black" style="letter-spacing: 0.15em;">
                                {{ strtoupper(config('app.name', 'CHAYETINATS')) }}
                            </span>
                        @endif
                    </a>
                </div>
            </x-slot>

            <x-slot:content class="!p-0">
                <div class="border-b border-zinc-200 p-4">
                    <div class="grid grid-cols-[auto_1fr] items-center gap-4 rounded-xl border border-zinc-200 p-2.5">
                        <div>
                            <img
                                src="{{ auth()->user()?->image_url ?? bagisto_asset('images/user-placeholder.png') }}"
                                class="h-[60px] w-[60px] rounded-full max-md:rounded-full"
                            >
                        </div>

                        @guest('customer')
                            <a
                                href="{{ route('shop.customer.session.create') }}"
                                class="flex text-base font-medium"
                            >
                                @lang('shop::app.components.layouts.header.mobile.login')

                                <i class="icon-double-arrow text-2xl ltr:ml-2.5 rtl:mr-2.5"></i>
                            </a>
                        @endguest

                        @auth('customer')
                            <div
                                class="flex flex-col justify-between gap-2.5 max-md:gap-0"
                                v-pre
                            >
                                <p class="font-mediums break-all text-2xl max-md:text-xl">Hello! {{ auth()->user()?->first_name }}</p>

                                <p class="text-zinc-500 no-underline max-md:text-sm">{{ auth()->user()?->email }}</p>
                            </div>
                        @endauth
                    </div>
                </div>

                <div class="flex flex-col px-6 py-4">
                    @if ($shopCategories->isNotEmpty())
                        <details class="group border-b border-zinc-100 last:border-b-0">
                            <summary class="flex cursor-pointer list-none items-center justify-between py-3 text-base font-medium uppercase tracking-wide text-black [&::-webkit-details-marker]:hidden">
                                <span>SHOP</span>
                                <span class="icon-arrow-down text-2xl transition-transform group-open:rotate-180"></span>
                            </summary>

                            <div class="flex flex-col pb-2 ltr:pl-4 rtl:pr-4">
                                @foreach ($shopCategories as $category)
                                    <a
                                        href="{{ url($category->slug) }}"
                                        class="py-2 text-sm uppercase tracking-wide text-zinc-700"
                                    >
                                        {{ $category->name }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <a
                            href="{{ url('/shop') }}"
                            class="py-3 text-base font-medium uppercase tracking-wide text-black"
                        >
                            SHOP
                        </a>
                    @endif

                    <a
                        href="{{ url('/lookbook') }}"
                        class="py-3 text-base font-medium uppercase tracking-wide text-black"
                    >
                        LOOKBOOK
                    </a>

                    <a
                        href="{{ url('/art') }}"
                        class="py-3 text-base font-medium uppercase tracking-wide text-black"
                    >
                        ART
                    </a>
                </div>
            </x-slot>

            <x-slot:footer>
                @if (core()->getCurrentChannel()->locales()->count() > 1 || core()->getCurrentChannel()->currencies()->count() > 1)
                    <div class="fixed bottom-0 z-10 grid w-full max-w-full grid-cols-[1fr_auto_1fr] items-center justify-items-center border-t border-zinc-200 bg-white px-5 ltr:left-0 rtl:right-0">
                        <x-shop::drawer
                            position="bottom"
                            width="100%"
                        >
                            <x-slot:toggle>
                                <div
                                    class="flex cursor-pointer items-center gap-x-2.5 px-2.5 py-3.5 text-lg font-medium uppercase max-md:py-3 max-sm:text-base"
                                    role="button"
                                    v-pre
                                >
                                    {{ core()->getCurrentCurrency()->symbol . ' ' . core()->getCurrentCurrencyCode() }}
                                </div>
                            </x-slot>

                            <x-slot:header>
                                <div class="flex items-center justify-between">
                                    <p class="text-lg font-semibold">
                                        @lang('shop::app.components.layouts.header.mobile.currencies')
                                    </p>
                                </div>
                            </x-slot>

                            <x-slot:content class="!px-0">
                                <div
                                    class="overflow-auto"
                                    :style="{ height: getCurrentScreenHeight }"
                                >
                                    <v-currency-switcher></v-currency-switcher>
                                </div>
                            </x-slot>
                        </x-shop::drawer>

                        <span class="h-5 w-0.5 bg-zinc-200"></span>

                        <x-shop::drawer
                            position="bottom"
                            width="100%"
                        >
                            <x-slot:toggle>
                                <div
                                    class="flex cursor-pointer items-center gap-x-2.5 px-2.5 py-3.5 text-lg font-medium uppercase max-md:py-3 max-sm:text-base"
                                    role="button"
                                    v-pre
                                >
                                    <img
                                        src="{{ ! empty(core()->getCurrentLocale()->logo_url)
                                                ? core()->getCurrentLocale()->logo_url
                                                : bagisto_asset('images/default-language.svg')
                                            }}"
                                        class="h-full"
                                        alt="Default locale"
                                        width="24"
                                        height="16"
                                    />

                                    {{ core()->getCurrentChannel()->locales()->orderBy('name')->where('code', app()->getLocale())->value('name') }}
                                </div>
                            </x-slot>

                            <x-slot:header>
                                <div class="flex items-center justify-between">
                                    <p class="text-lg font-semibold">
                                        @lang('shop::app.components.layouts.header.mobile.locales')
                                    </p>
                                </div>
                            </x-slot>

                            <x-slot:content class="!px-0">
                                <div
                                    class="overflow-auto"
                                    :style="{ height: getCurrentScreenHeight }"
                                >
                                    <v-locale-switcher></v-locale-switcher>
                                </div>
                            </x-slot>
                        </x-shop::drawer>
                    </div>
                @endif
            </x-slot>
        </x-shop::drawer>
    </script>

    <script type="module">
        app.component('v-mobile-nav', {
            template: '#v-mobile-nav-template',

            data() {
                return {
                    searchOpen: false,
                };
            },

            methods: {
                toggleSearch() {
                    this.searchOpen = ! this.searchOpen;

                    if (this.searchOpen) {
                        this.$nextTick(() => this.$refs.searchInput?.focus());
                    }
                },
            },
        });

        app.component('v-mobile-drawer', {
            template: '#v-mobile-drawer-template',

            computed: {
                getCurrentScreenHeight() {
                    return window.innerHeight - (window.innerWidth < 920 ? 61 : 0) + 'px';
                },
            },

            methods: {
                onDrawerClose() {},
            },
        });
    </script>
@endPushOnce
