// Use React hooks from the global React object
const { useState, useEffect } = React;

// Debug mode flag
const DEBUG_MODE = false;

// Debug logging function
function debugLog(...args) {
    if (DEBUG_MODE) {
        console.log(...args);
    }
}

const InteractiveQuestionnaire = () => {
    const [questionsData, setQuestionsData] = useState(null);
    const [currentNode, setCurrentNode] = useState('start');
    const [history, setHistory] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [recommendedProductSlug, setRecommendedProductSlug] = useState(null);

    useEffect(() => {
        debugLog("Component mounted. Attempting to fetch data...");
        debugLog("Global questionnaireData:", window.questionnaireData);

        if (!window.questionnaireData || !window.questionnaireData.jsonUrl) {
            debugLog("jsonUrl is not available in global questionnaireData");
            setError("Configuration error: JSON URL not provided");
            setIsLoading(false);
            return;
        }

        fetch(window.questionnaireData.jsonUrl)
            .then(response => {
                debugLog("Fetch response received");
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                debugLog("Data successfully parsed:", data);
                setQuestionsData(data);
                setIsLoading(false);
            })
            .catch(error => {
                debugLog('Fetch error:', error);
                setError('Error loading questionnaire data: ' + error.message);
                setIsLoading(false);
            });
    }, []);

    useEffect(() => {
        if (recommendedProductSlug) {
            updateHandPickedProducts(recommendedProductSlug);
        } else {
            hideHandPickedProducts();
        }
    }, [recommendedProductSlug]);

    const handleAnswer = (nextNode, answerText) => {
        setHistory([...history, { node: currentNode, answer: answerText }]);
        setCurrentNode(nextNode);
    
        // Check if the next node has a recommendation and product slug
        const nextNodeData = questionsData.questions[nextNode];
        if (nextNodeData && nextNodeData.recommendation && nextNodeData.productSlug) {
            setRecommendedProductSlug(nextNodeData.productSlug);
        } else {
            setRecommendedProductSlug(null);
        }
    };
    
    const goBack = (index) => {
        const previousNode = history[index].node;
        setCurrentNode(previousNode);
        setHistory(history.slice(0, index));
        setRecommendedProductSlug(null);
    };
    
    const updateHandPickedProducts = (productSlug) => {
        const productBlock = document.getElementById('recommended-product');
        if (productBlock) {
            productBlock.style.display = 'block';
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.questionnaireData.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        productBlock.innerHTML = xhr.responseText;
                    } else {
                        console.error('AJAX request failed:', xhr.status, xhr.statusText);
                    }
                }
            };
            const params = 'action=display_uncropped_product&product_slug=' + encodeURIComponent(productSlug);
            console.log('Sending AJAX request with params:', params);
            xhr.send(params);
        }
    };
    
    const hideHandPickedProducts = () => {
        const productBlock = document.getElementById('recommended-product');
        if (productBlock) {
            productBlock.style.display = 'none';
            productBlock.innerHTML = '';
        }
    };

    const renderNode = () => {
        if (!questionsData || !questionsData.questions) {
            debugLog("renderNode: No questionnaire data available");
            return null;
        }

        const node = questionsData.questions[currentNode];
        if (!node) {
            debugLog("renderNode: Invalid node:", currentNode);
            return null;
        }

        if (node.recommendation) {
            return React.createElement('div', { className: "bg-green-100 p-4 rounded-lg" },
                React.createElement('h2', { className: "text-xl font-bold mb-2" }, "Recommendation:"),
                React.createElement('p', null, node.recommendation)
            );
        }

        return React.createElement(React.Fragment, null,
            React.createElement('h2', { className: "text-xl font-bold mb-4" }, node.question),
            React.createElement('div', { className: "space-y-2" },
                node.answers.map((answer, index) =>
                    React.createElement('button', {
                        key: index,
                        className: "w-full p-2 text-left bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors",
                        onClick: () => handleAnswer(answer.next, answer.text)
                    }, answer.text)
                )
            )
        );
    };

    debugLog("Render cycle. isLoading:", isLoading, "error:", error, "questionsData:", questionsData);

    if (isLoading) {
        return React.createElement('div', null, "Loading...");
    }

    if (error) {
        return React.createElement('div', null, "Error: ", error);
    }

    if (!questionsData || !questionsData.questions) {
        return React.createElement('div', null, "No questionnaire data available.");
    }

    return React.createElement('div', { className: "max-w-2xl mx-auto p-4" },
        React.createElement('h1', { className: "text-3xl font-bold mb-6" }, questionsData.metadata.title),
        React.createElement('p', { className: "mb-4" }, questionsData.metadata.introduction),
        history.length > 0 && React.createElement('div', { className: "mb-4 space-y-2" },
            history.map((item, index) =>
                React.createElement('button', {
                    key: index,
                    className: "flex items-center text-blue-500 hover:bg-blue-600",
                    onClick: () => goBack(index)
                },
                    "← Go Back to: ", questionsData.questions[item.node].question,
                    React.createElement('span', { className: "ml-2 text-gray-600" }, `(${item.answer})`)
                )
            )
        ),
        renderNode()
    );
};

// Render the app
ReactDOM.render(
    React.createElement(React.StrictMode, null,
        React.createElement(InteractiveQuestionnaire)
    ),
    document.getElementById('questionnaire-root')
);