Asks a series of leading questions. Each question depends on the answer to previous questions.
Questions defined in js/questions.json
styles defined in the css/questionnaire.css
how both are handled is in js/questionnaire.js

To Do: I have realised that a better structure for the json data would be as in the file js/betterquestionsstructure.json, but currently this file does nothing. Problem is the js code references the indexes of the previous nodes, so the historical data works only with the numbers. I had a go at changing the whole code to work with the better structure but came up with a lot of problems once it was rendered on the front end. E.g. it was difficult to keep track of the "history" section when there isn't a numbered identifier to refer to.

Anyway, if someone with a wordpress (woocommerce) site wants to create their own questionnaire, you'll have to do the work to create your own version of js/questions.json for it to display your questions and recommendations on the front end.