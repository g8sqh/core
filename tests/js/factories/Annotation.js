describe('The Annotation resource factory', function () {
	var $httpBackend;

	beforeEach(module('dias.api'));

	beforeEach(inject(function($injector) {
		var annotation = {
			id: 1,
			image_id: 1,
			shape_id: 2
		};

		// Set up the mock http service responses
		$httpBackend = $injector.get('$httpBackend');
		
		$httpBackend.when('GET', '/api/v1/annotations/1')
		            .respond(annotation);

		$httpBackend.when('PUT', '/api/v1/annotations/1')
		            .respond(200);
		
		$httpBackend.when('DELETE', '/api/v1/annotations/1')
		            .respond(200);

		$httpBackend.when('GET', '/api/v1/images/1/annotations')
		            .respond([annotation]);
		
		$httpBackend.when('POST', '/api/v1/images/1/annotations')
		            .respond(annotation);
	}));

	afterEach(function() {
		$httpBackend.verifyNoOutstandingExpectation();
		$httpBackend.verifyNoOutstandingRequest();
	});

	it('should show an annotation', inject(function (Annotation) {
		$httpBackend.expectGET('/api/v1/annotations/1');
		var annotation = Annotation.get({id: 1}, function () {
			expect(annotation.id).toEqual(1);
		});
		$httpBackend.flush();
	}));

	it('should delete an annotation', inject(function (Annotation) {
		$httpBackend.expectDELETE('/api/v1/annotations/1');
		Annotation.delete({id: 1});
		$httpBackend.flush();
	}));

	it('should save an annotation', inject(function (Annotation) {
		$httpBackend.expectPUT('/api/v1/annotations/1', {
			id: 1, image_id: 1, shape_id: 2, points: [{x: 10, y: 10}]
		});
		var annotation = Annotation.get({id: 1}, function () {
			annotation.points = [{x: 10, y: 10}];
			annotation.$save();
		});
		Annotation.save({
			id: 1, image_id: 1, shape_id: 2, points: [{x: 10, y: 10}]
		});
		$httpBackend.flush();
	}));

	it('should query image annotations', inject(function (Annotation) {
		$httpBackend.expectGET('/api/v1/images/1/annotations');
		var annotations = Annotation.query({id: 1}, function () {
			var annotation = annotations[0];
			expect(annotation instanceof Annotation).toBe(true);
			expect(annotation.shape_id).toEqual(2);
		});
		$httpBackend.flush();
	}));

	it('should add new annotations', inject(function (Annotation) {
		$httpBackend.expectPOST('/api/v1/images/1/annotations', {
			shape_id: 2, points: [{x: 10, y: 20}], id: 1, label_id: 1, confidence: 1
		});
		var annotation = Annotation.add(
			{shape_id: 2, points: [{x: 10, y: 20}], id: 1, label_id: 1, confidence: 1},
			function () { expect(annotation.id).toEqual(1); }
		);
		$httpBackend.flush();
	}));
});