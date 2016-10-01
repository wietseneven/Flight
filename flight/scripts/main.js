var flightApp = {
  el: {
    template: $('#flightView')
  },
  templateRoot: 'views/templates/',
  setup: function () {
    console.log('hi');
  //  flightApp.createIndex();
    flightApp.createChat({
      personName: 'Mohammed'
    });
  },
  getTemplate: function(path, callback){
    console.log('Getting '+path);
    var source;
    var template;

    $.ajax({
      url: flightApp.templateRoot + path + '.html',
      success: function(data) {
        source    = data;
        template  = Handlebars.compile(source);

        //execute the callback if passed
        if (callback) callback(template);
      },
      error: function(data){
        var errorText = 'Template kan niet geladen worden: ';
        errorText += data.statusText;
        flightApp.error(errorText);
      }
    });
  },
  createIndex: function() {
    flightApp.getTemplate('index', function(template){
      flightApp.el.template.html(template);
    });
  },
  createChat: function(args) {
    chat(args);
  }
};

var newMessage = function(chatID, message, creator) {
  flightApp.getTemplate('message', function(template){
    var messageData = {
      personName: message,
      creator: creator
    };
    $('.chat .messageList').append(template(messageData));
  });
};

$(document).ready(function() {
  flightApp.setup();
});
