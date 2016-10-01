var chat = function(args) {
  var chatName = args.personName;
  var chatID   = 'chat-'+Math.floor(Math.random() * 100);

  var createChat = {
    setup: function() {
      flightApp.getTemplate('chat', function (template) {

        var vars = {
          chatID: chatID,
          personName: chatName
        };

        flightApp.el.template.html(template(vars));
        setTimeout(function() {
          createChat.watchForm();
        }, 1000);
        //createChat.watchForm();
      });
    },
    watchForm: function() {
      var form = $('#'+chatID+' .chatbox form');
      alert('#'+chatID+' .chatbox form');
      form.addClass('fasdf');
      form.submit(function(e){
        e.preventDefault();
        alert('submitted');
      });
    }
  };

  createChat.setup();
};
