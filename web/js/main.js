smoothScroll.init();

var $grid = $('#gallery').masonry({
  itemSelector: '.image',
  columnWidth: '.grid-sizer',
  percentPosition: true
});

$grid.imagesLoaded().progress(function() {
  $grid.masonry('layout');
});

$('#home').css({'background-image': 'url(../images/bg' + Math.floor(Math.random()*5) + '.jpg)'});

// Load last images
$("#load").click(function() {
  var lastDate = $(".swipe img").last()[0].getAttribute("data-date-taken");
  $.get("from/"+lastDate, {}, function(data) {

    $("#gallery").append(data);
    var $content = $(data);
    $grid.append($content).masonry('appended', $content);
  });
});
