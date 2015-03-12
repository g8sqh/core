/**
 * @ngdoc factory
 * @name MediaType
 * @memberOf dias.core
 * @description Provides the resource for media types.
 * @requires $resource
 * @returns {Object} A new [ngResource](https://docs.angularjs.org/api/ngResource/service/$resource) object
 * @example
// get all media types
var mediaTypes = MediaType.query(function () {
   console.log(mediaTypes); // [{id: 1, name: "time-series"}, ...]
});

// get one media type
var mediaType = MediaType.get({id: 1}, function () {
   console.log(mediaType); // {id: 1, name: "time-series"}
});
 *
 */
angular.module('dias.core').factory('MediaType', function ($resource) {
	"use strict";

	return $resource('/api/v1/media-types/:id', { id: '@id' });
});