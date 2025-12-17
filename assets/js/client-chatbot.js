document.addEventListener('DOMContentLoaded', () => {
    const chatbotBubble = document.getElementById('chatbot-bubble');
    const chatbotWindow = document.getElementById('chatbot-window');
    const chatbotClose = document.getElementById('chatbot-close');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSend = document.getElementById('chatbot-send');
    const chatbotChips = document.getElementById('chatbot-chips');
    const logoutBtn = document.getElementById('client-logout-btn');

    // --- Chat History Persistence ---
    function saveChatHistory() {
        if (chatbotMessages) {
            sessionStorage.setItem('kpf_chat_history', chatbotMessages.innerHTML);
            // Also save the chatbot's visibility state
            sessionStorage.setItem('kpf_chatbot_open', !chatbotWindow.classList.contains('d-none'));
        }
    }

    function loadChatHistory() {
        const savedHistory = sessionStorage.getItem('kpf_chat_history');
        if (savedHistory && chatbotMessages) {
            chatbotMessages.innerHTML = savedHistory;
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }

        // Restore chatbot visibility state
        const wasOpen = sessionStorage.getItem('kpf_chatbot_open') === 'true';
        if (chatbotWindow) {
            if (wasOpen) {
                chatbotWindow.classList.remove('d-none');
            } else {
                chatbotWindow.classList.add('d-none');
            }
        }
    }
    
    // Clear history on logout
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            sessionStorage.removeItem('kpf_chat_history');
            sessionStorage.removeItem('kpf_chatbot_open');
        });
    }

    // Load history and state on init
    loadChatHistory();

    if (chatbotBubble && chatbotWindow && chatbotClose) {
        chatbotBubble.addEventListener('click', () => {
            chatbotWindow.classList.toggle('d-none');
            saveChatHistory(); // Save state when opened/closed
        });

        chatbotClose.addEventListener('click', () => {
            chatbotWindow.classList.add('d-none');
            saveChatHistory(); // Save state when closed
        });

        if (chatbotSend && chatbotInput) {
            chatbotSend.addEventListener('click', sendMessage);
            chatbotInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });
        }
        
        // Chip Click Handling
        if (chatbotChips) {
            chatbotChips.addEventListener('click', (e) => {
                if (e.target.classList.contains('chip')) {
                    const message = e.target.getAttribute('data-message');
                    chatbotInput.value = message;
                    sendMessage();
                }
            });
        }
    }

    function sendMessage() {
        const userMessage = chatbotInput.value.trim();
        if (userMessage === '') return;

        appendMessage(userMessage, 'user');
        chatbotInput.value = '';
        
        const formData = new FormData();
        formData.append('message', userMessage);

        // Fetch requires window.dashboardConfig.csrfToken and window.dashboardConfig.userHeight
        // which are set in client/dashboard.php.
        // For general client pages, this might not be available or needed.
        // We need a more generic way to get csrfToken if it's not dashboard-specific.
        // For now, I'll rely on it being available globally if this JS is loaded on all pages.
        // Better: Pass csrfToken to chatbot.js through a global variable set in client_header.php or client_footer.php.

        // Assuming csrfToken is available globally (e.g., from client_header.php or client_footer.php)
        // If not, it needs to be injected into the DOM as a meta tag or a global JS var.
        // For now, I will assume it is passed in the global `window.clientConfig` object.

        formData.append('csrf_token', window.clientConfig.csrfToken);


        fetch('../api/client_chatbot_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Check for navigation tag
            const navRegex = /\[NAVIGATE:(.*?)\]/;
            const match = data.reply.match(navRegex);
            
            let displayMessage = data.reply;

            if (match) {
                const url = match[1];
                // Remove the tag from display
                displayMessage = displayMessage.replace(navRegex, '');
                appendMessage(displayMessage, 'bot');
                
                // Redirect after small delay
                setTimeout(() => {
                    window.location.href = url;
                }, 1500);
            } else {
                appendMessage(displayMessage, 'bot'); // Use displayMessage here
            }
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        })
        .catch(error => {
            console.error('Error:', error);
            appendMessage("Oops! Something went wrong. Please try again later.", 'bot');
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        });
    }

    function appendMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message', sender);
        
        // Check for button syntax [BUTTON:Label|URL]
        // Regex looks for [BUTTON: then anything until | then anything until ]
        const buttonRegex = /\[BUTTON:(.*?)\|(.*?)\]/g;
        
        if (sender === 'bot' && buttonRegex.test(text)) {
            // Replace the special syntax with an HTML anchor tag
            const formattedText = text.replace(buttonRegex, (match, label, url) => {
                return `<br><a href="${url}" class="chip mt-2">${label}</a>`;
            });
            messageDiv.innerHTML = formattedText; // Use innerHTML to render the link
        } else {
            messageDiv.textContent = text; // Use textContent for safety if no button
        }

        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        saveChatHistory(); // Save after appending
    }
});