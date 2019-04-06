var Controls = (function(Controls) {
    "use strict";

	// Check for double inclusion
	if (Controls.addMouseHandler)
		return Controls;

	Controls.addMouseHandler = function (domObject, drag, move, zoom) {
		var startDragX = null,
		    startDragY = null;
		var lastMove = null;
		var mouseMode = null;   
		    
        domObject.addEventListener("touchstart", function (e) {
            var touch = e.touches[0];
            lastMove = touch;
            var mouseEvent = new MouseEvent("mousedown", {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            if (e.target == domObject) {
                e.preventDefault();
            }
            domObject.dispatchEvent(mouseEvent);
        }, false);
        domObject.addEventListener("touchend", function (e) {
            var touch = lastMove;
            var mouseEvent = new MouseEvent("mouseup", {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            if (e.target == domObject) {
                e.preventDefault();
            }
            domObject.dispatchEvent(mouseEvent);
        }, false);
        domObject.addEventListener("touchmove", function (e) {
            var touch = e.touches[0];
            lastMove = touch;
            var mouseEvent = new MouseEvent("mousemove", {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            if (e.target == domObject) {
                e.preventDefault();
            }
            domObject.dispatchEvent(mouseEvent);
        }, false);

		function mouseDownHandler(e) {
		    if(mouseMode) return;
		
			startDragX = e.clientX;
			startDragY = e.clientY;
			
			mouseMode=e.which;

			e.preventDefault();
		}

		function mouseMoveHandler(e) {
			if (startDragX === null || startDragY === null)
				return;

			if (mouseMode == 1 && drag)
				drag(e.clientX - startDragX, e.clientY - startDragY);
			
			if (mouseMode == 2 && move)
				move(e.clientX - startDragX, e.clientY - startDragY);
				
			if (mouseMode == 3 && zoom)
				zoom(e.clientX - startDragX, e.clientY - startDragY);

			startDragX = e.clientX;
			startDragY = e.clientY;

			e.preventDefault();
		}

		function mouseUpHandler(e) {
			mouseMoveHandler.call(this, e);
			startDragX = null;
			startDragY = null;
			
			mouseMode=null;

			e.preventDefault();
		}

		domObject.addEventListener("mousedown", mouseDownHandler);
		domObject.addEventListener("mousemove", mouseMoveHandler);
		domObject.addEventListener("mouseup", mouseUpHandler);
	};
	return Controls;
}(Controls || {}))
