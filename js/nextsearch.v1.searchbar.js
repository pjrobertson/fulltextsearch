/*
 * FullNextSearch - Full Text Search your Nextcloud.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 */

/** global: OCA */
/** global: nav */
/** global: _ */
/** global: api */
/** global: search */
/** global: result */
/** global: nextSearch */
/** global: next_settings */


var searchbox = {
	searchTimeout: null,
	search_icon_close: null,
	search_icon: null,
	search_input: null,
	search_form: null
};


var searchbar = {

	init: function () {
		var divHeaderRight = $('div.header-right');

		searchbox.search_div = $('<div>', {class: 'next_search_div'});
		divHeaderRight.prepend(searchbox.search_div);

		searchbox.search_icon = $('<div>', {class: 'icon-fullnextsearch'});
		searchbox.search_icon.css('background-image',
			"url('/apps/fullnextsearch/img/fullnextsearch.svg')");
		searchbox.search_icon.fadeTo(0, 0.7);
		searchbox.search_div.append(searchbox.search_icon);


		searchbox.search_form = $('<div>');
		searchbox.search_form.fadeTo(0, 0);

		searchbox.search_input = $('<input>', {
			id: 'next_search_input',
			placeholder: 'Search'
		});
		searchbox.search_form.append(searchbox.search_input);

		searchbox.search_icon_close = $('<div>', {class: 'icon-close-white icon-close-fullnextsearch'});
		searchbox.search_icon_close.fadeTo(0, 0);
		searchbox.search_icon_close.on('click', function () {
			next_settings.lockSearchbox = false;
			searchbox.search_icon_close.stop().fadeTo(100, 0);
			searchbox.search_input.val('');
			nav.onSearchReset();
		});
		searchbox.search_form.append(searchbox.search_icon_close);

		searchbox.search_div.append(searchbox.search_form);
		searchbox.search_div.hover(function () {
			searchbox.search_icon.stop().fadeTo(100, 0);
			searchbox.search_form.stop().fadeTo(100, 0.8);
		}, function () {
			if (next_settings.lockSearchbox === true) {
				return;
			}
			searchbox.search_form.stop().fadeTo(500, 0);
			searchbox.search_icon.stop().fadeTo(800, 0.7);
		});

		searchbox.search_input.on('focus', function () {
			next_settings.lockSearchbox = true;
			searchbox.search_icon_close.stop().fadeTo(200, 1);
		});

		searchbox.search_input.on('input', function () {


			if ($(this).val() === '') {
				nav.onSearchReset();
			}

			if (next_settings.parentHasMethod('onEntryGenerated')) {
				next_settings.parent.onEntryGenerated();
			}

			if (searchbox.searchTimeout === null && searchbar.initSearch(false)) {
				searchbox.searchTimeout = _.delay(function () {
					searchbar.initSearch(false);
					searchbox.searchTimeout = null;
				}, 2000);
			}
		});
	},


	initSearch: function (force) {
		var search = searchbox.search_input.val();

		if (!force && search.length < 3) {
			return false;
		}

		nextSearch.search(next_settings.searchProviderId, search, searchbar.searchResult);

		return true;
	}


};

