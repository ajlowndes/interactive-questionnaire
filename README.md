# Interactive Questionnaire

A WordPress plugin that creates an interactive questionnaire to guide users through a series of questions and provide product recommendations based on their answers.

## Features

- Create multiple questionnaires with a user-friendly admin interface
- Build complex decision trees with questions, answers, and recommendations
- Link recommendations to WooCommerce products
- Shortcode system for easy embedding on any page or post
- Responsive design that works on all devices
- Built with React for a smooth user experience
- Database storage for better performance and scalability

## Installation

1. Upload the `interactive-questionnaire` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'Questionnaires' menu to create and manage your questionnaires
4. Use the shortcode `[questionnaire id="1"]` to display your questionnaire on any page or post

## Usage

### Creating a Questionnaire

1. Navigate to Questionnaires > Add New
2. Enter a title and optional introduction text
3. Save the questionnaire
4. You'll be redirected to the Nodes management page where you can start building your questionnaire

### Building the Question Flow

1. The start node is automatically created for you
2. Edit the start node to add your first question
3. Add answers to the question, each pointing to a next node
4. Add additional question nodes and recommendation nodes as needed
5. Link answers to existing nodes to create your question flow

### Recommendation Nodes

Recommendation nodes can include:
- Text recommendation
- Product slug (for WooCommerce integration)

### Displaying the Questionnaire

Use the shortcode `[questionnaire id="X"]` where X is the ID of your questionnaire.

Example: `[questionnaire id="1"]`

## Development

This plugin is built using:
- PHP for the backend functionality
- React for the frontend interface
- WordPress database for data storage
- WooCommerce integration for product recommendations

## License

GPL v2 or later