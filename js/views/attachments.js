/**
 * Mail
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Luc Calaresu <dev@calaresu.com>
 * @copyright Christoph Wurst 2015
 */

define(function(require) {
	'use strict';

	var _ = require('underscore');
	var $ = require('jquery');
	var Marionette = require('marionette');
	var OC = require('OC');
	var Handlebars = require('handlebars');
	var Radio = require('radio');
	var AttachmentView = require('views/attachment');
	var AttachmentsTemplate = require('text!templates/attachments.html');
	var LocalAttachment = require('models/localattachment');

	return Marionette.CompositeView.extend({
		collection: null,
		childView: AttachmentView,
		childViewContainer: 'ul',
		template: Handlebars.compile(AttachmentsTemplate),
		ui: {
			'fileInput': '#local-attachments'
		},
		events: {
			'click #add-cloud-attachment': 'addCloudAttachment',
			'click #add-local-attachment': 'addLocalAttachment',
			'change @ui.fileInput': 'onAttachmentsChanged'
		},
		initialize: function(options) {
			this.collection = options.collection;
		},
		addCloudAttachment: function() {
			var title = t('mail', 'Choose a file to add as attachment');
			OC.dialogs.filepicker(title, _.bind(this.onCloudFileSelected, this));
		},
		onCloudFileSelected: function(path) {
			this.collection.add({
				fileName: path
			});
		},
		addLocalAttachment: function() {
			/* reset the fileInput value to allow sending the same file several */
			/* times. e.g. if the previous upload failed. */
			this.ui.fileInput.val('');
			this.ui.fileInput.click();
		},
		onAttachmentsChanged: function(event) {
			var files = event.target.files;
			var _this = this;
			for (var i = 0; i < files.length; i++) {
				var file = files[i];

				// TODO check file size?
				var attachment = new LocalAttachment({
					fileName: file.name
				});

				var uploading = Radio.attachment.request(
					'upload:local',	file, attachment);
				$.when(uploading).fail(function() {
					Radio.ui.trigger(
						'error:show',
						t('mail', 'An error occurred while uploading an attachment'));
				});

				_this.collection.add(attachment);
			}
		}
	});
});
