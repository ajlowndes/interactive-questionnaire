/* Interactive Questionnaire Admin JS */
jQuery(document).ready(function($) {
    // Handle node type change
    $('.iq-node-type-selector').on('change', function() {
        var nodeType = $(this).val();
        
        if (nodeType === 'question') {
            $('.iq-question-section, .iq-answers-section').show();
            $('.iq-recommendation-section').hide();
        } else {
            $('.iq-question-section, .iq-answers-section').hide();
            $('.iq-recommendation-section').show();
        }
    });
    
    // Add answer
    $('.iq-add-answer').on('click', function() {
        var container = $('#iq-answers-container');
        var template = $('.iq-answer-template').html();
        var count = container.find('.iq-answer-item').length;
        
        // Replace INDEX with the current count
        template = template.replace(/INDEX/g, count);
        
        // Add the new answer item
        container.append(template);
        
        // Update titles
        updateAnswerTitles();
    });
    
    // Remove answer
    $(document).on('click', '.iq-remove-answer', function() {
        $(this).closest('.iq-answer-item').remove();
        
        // Update titles
        updateAnswerTitles();
    });
    
    // Update answer titles
    function updateAnswerTitles() {
        $('.iq-answer-item h4').each(function(index) {
            $(this).text('Answer ' + (index + 1));
        });
        
        // Update input names
        $('.iq-answer-item').each(function(index) {
            $(this).find('input, select').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/answers\[\d+\]/, 'answers[' + index + ']');
                    $(this).attr('name', name);
                }
            });
        });
    }
    
    // Copy shortcode button
    $('.copy-shortcode').on('click', function() {
        var shortcode = $(this).data('shortcode');
        
        // Create a temporary textarea element
        var textarea = document.createElement('textarea');
        textarea.value = shortcode;
        document.body.appendChild(textarea);
        
        // Select and copy the text
        textarea.select();
        document.execCommand('copy');
        
        // Remove the temporary element
        document.body.removeChild(textarea);
        
        // Show a message
        $(this).text('Copied!');
        setTimeout(function() {
            $('.copy-shortcode').text('Copy');
        }, 2000);
    });
});