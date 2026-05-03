{!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.before') !!}

<v-desktop-nav></v-desktop-nav>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-desktop-nav-template"
    >
        <div class="relative w-full">
            <div class="grid min-h-[78px] w-full grid-cols-3 items-center px-[60px] max-1180:px-8">
                <!-- Left Navigation Links -->
                <div class="flex items-center gap-x-8 max-[1180px]:gap-x-5">
                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.nav_links.before') !!}

                    <a
                        href="{{ url('/shop') }}"
                        class="text-sm font-medium uppercase tracking-wide text-black hover:opacity-60"
                    >
                        SHOP
                    </a>

                    <a
                        href="{{ url('/lookbook') }}"
                        class="text-sm font-medium uppercase tracking-wide text-black hover:opacity-60"
                    >
                        LOOKBOOK
                    </a>

                    <a
                        href="{{ url('/art') }}"
                        class="text-sm font-medium uppercase tracking-wide text-black hover:opacity-60"
                    >
                        ART
                    </a>

                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.nav_links.after') !!}
                </div>

                <!-- Center Logo -->
                <div class="flex justify-center">
                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.logo.before') !!}

                    <a
                        href="{{ route('shop.home.index') }}"
                        aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.bagisto')"
                        class="block"
                    >
                        @if (core()->getCurrentChannel()->logo_url)
                            <img
                                src="{{ core()->getCurrentChannel()->logo_url }}"
                                alt="{{ config('app.name') }}"
                                class="h-7 w-auto"
                            >
                        @else
                            <span class="text-2xl font-extrabold text-black" style="letter-spacing: 0.15em;">
                                {{ strtoupper(config('app.name', 'CHAYETINATS')) }}
                            </span>
                        @endif
                    </a>

                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.logo.after') !!}
                </div>

                <!-- Right Navigation Icons -->
                <div class="flex items-center justify-end gap-x-6">
                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.profile.before') !!}

                    <x-shop::dropdown position="bottom-{{ core()->getCurrentLocale()->direction === 'ltr' ? 'right' : 'left' }}">
                        <x-slot:toggle>
                            <span
                                class="icon-users inline-block cursor-pointer text-2xl"
                                role="button"
                                aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.profile')"
                                tabindex="0"
                            ></span>
                        </x-slot>

                        @guest('customer')
                            <x-slot:content>
                                <div class="grid gap-2.5">
                                    <p class="font-dmserif text-xl">
                                        @lang('shop::app.components.layouts.header.desktop.bottom.welcome-guest')
                                    </p>

                                    <p class="text-sm">
                                        @lang('shop::app.components.layouts.header.desktop.bottom.dropdown-text')
                                    </p>
                                </div>

                                <p class="mt-3 w-full border border-zinc-200"></p>

                                {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.customers_action.before') !!}

                                <div class="mt-6 flex gap-4">
                                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.sign_in_button.before') !!}

                                    <a
                                        href="{{ route('shop.customer.session.create') }}"
                                        class="primary-button m-0 mx-auto block w-max rounded-2xl px-7 text-center text-base max-md:rounded-lg ltr:ml-0 rtl:mr-0"
                                    >
                                        @lang('shop::app.components.layouts.header.desktop.bottom.sign-in')
                                    </a>

                                    <a
                                        href="{{ route('shop.customers.register.index') }}"
                                        class="secondary-button m-0 mx-auto block w-max rounded-2xl border-2 px-7 text-center text-base max-md:rounded-lg max-md:py-3 ltr:ml-0 rtl:mr-0"
                                    >
                                        @lang('shop::app.components.layouts.header.desktop.bottom.sign-up')
                                    </a>

                                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.sign_up_button.after') !!}
                                </div>

                                {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.customers_action.after') !!}
                            </x-slot>
                        @endguest

                        @auth('customer')
                            <x-slot:content class="!p-0">
                                <div class="grid gap-2.5 p-5 pb-0">
                                    <p class="font-dmserif text-xl" v-pre>
                                        @lang('shop::app.components.layouts.header.desktop.bottom.welcome')’
                                        {{ auth()->guard('customer')->user()->first_name }}
                                    </p>

                                    <p class="text-sm">
                                        @lang('shop::app.components.layouts.header.desktop.bottom.dropdown-text')
                                    </p>
                                </div>

                                <p class="mt-3 w-full border border-zinc-200"></p>

                                <div class="mt-2.5 grid gap-1 pb-2.5">
                                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.profile_dropdown.links.before') !!}

                                    <a
                                        class="cursor-pointer px-5 py-2 text-base hover:bg-gray-100"
                                        href="{{ route('shop.customers.account.profile.index') }}"
                                    >
                                        @lang('shop::app.components.layouts.header.desktop.bottom.profile')
                                    </a>

                                    <a
                                        class="cursor-pointer px-5 py-2 text-base hover:bg-gray-100"
                                        href="{{ route('shop.customers.account.orders.index') }}"
                                    >
                                        @lang('shop::app.components.layouts.header.desktop.bottom.orders')
                                    </a>

                                    @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
                                        <a
                                            class="cursor-pointer px-5 py-2 text-base hover:bg-gray-100"
                                            href="{{ route('shop.customers.account.wishlist.index') }}"
                                        >
                                            @lang('shop::app.components.layouts.header.desktop.bottom.wishlist')
                                        </a>
                                    @endif

                                    @auth('customer')
                                        <x-shop::form
                                            method="DELETE"
                                            action="{{ route('shop.customer.session.destroy') }}"
                                            id="customerLogout"
                                        />

                                        <a
                                            class="cursor-pointer px-5 py-2 text-base hover:bg-gray-100"
                                            href="{{ route('shop.customer.session.destroy') }}"
                                            onclick="event.preventDefault(); document.getElementById('customerLogout').submit();"
                                        >
                                            @lang('shop::app.components.layouts.header.desktop.bottom.logout')
                                        </a>
                                    @endauth

                                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.profile_dropdown.links.after') !!}
                                </div>
                            </x-slot>
                        @endauth
                    </x-shop::dropdown>

                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.profile.after') !!}

                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.search_bar.before') !!}

                    <button
                        type="button"
                        class="icon-search cursor-pointer text-2xl"
                        aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.search')"
                        @click="toggleSearch"
                    ></button>

                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.search_bar.after') !!}

                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.mini_cart.before') !!}

                    @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                        @include('shop::checkout.cart.mini-cart')
                    @endif

                    {!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.mini_cart.after') !!}
                </div>
            </div>

            <div
                v-show="searchOpen"
                class="absolute left-0 right-0 top-full z-20 border-b border-zinc-200 bg-white shadow-md"
            >
                <form
                    action="{{ route('shop.search.index') }}"
                    class="mx-auto flex max-w-3xl items-center gap-3 px-6 py-4"
                    role="search"
                >
                    <div class="icon-search pointer-events-none flex items-center text-2xl text-zinc-500"></div>

                    <input
                        ref="searchInput"
                        type="text"
                        name="query"
                        value="{{ request('query') }}"
                        class="block w-full bg-transparent py-2 text-sm font-medium text-gray-900 outline-none"
                        minlength="{{ core()->getConfigData('catalog.products.search.min_query_length') }}"
                        maxlength="{{ core()->getConfigData('catalog.products.search.max_query_length') }}"
                        placeholder="@lang('shop::app.components.layouts.header.desktop.bottom.search-text')"
                        aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.search-text')"
                        pattern="[^\\]+"
                        required
                    >

                    <button
                        type="button"
                        class="icon-cancel cursor-pointer text-2xl text-zinc-500"
                        aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.search')"
                        @click="toggleSearch"
                    ></button>
                </form>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-desktop-nav', {
            template: '#v-desktop-nav-template',

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
    </script>
@endPushOnce

{!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.after') !!}
