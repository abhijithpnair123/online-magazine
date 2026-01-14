document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("approvalModal");
    const modalTitle = document.getElementById("modalTitle");
    const modalBody = document.getElementById("modalBody");
    const modalContentIdInput = document.getElementById("modal_content_id");
    const closeButton = document.querySelector(".modal .close-button");

    // Function to open the modal
    function openModal() {
        modal.style.display = "block";
    }

    // Function to close the modal
    function closeModal() {
        modal.style.display = "none";
        modalBody.innerHTML = ''; // Clear previous content
    }

    // Attach event listener to all review buttons
    document.querySelectorAll('.review-btn').forEach(button => {
        button.addEventListener('click', function() {
            const contentId = this.getAttribute('data-content-id');
            
            // Set the content ID in the modal's hidden form field
            modalContentIdInput.value = contentId;

            // Fetch content details using AJAX
            fetch('get_content_details.php?id=' + contentId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                       modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                    } else {
                        // Build the HTML for the modal body
                        let contentHtml = `
                            <div class="info-grid">
                                <strong>Student:</strong><span>${escapeHtml(data.student_name)}</span>
                                <strong>Type:</strong><span>${escapeHtml(data.type_name)}</span>
                                <strong>Submitted:</strong><span>${new Date(data.submitted_date).toLocaleDateString()}</span>
                            </div>
                            <hr>
                            <h4>${escapeHtml(data.title)}</h4>
                            <div class="content-preview">
                        `;

                        if (data.type_name.toLowerCase() === 'image') {
                            contentHtml += `<a href="${escapeHtml(data.file_path)}" target="_blank"><img src="${escapeHtml(data.file_path)}" alt="Submission Image"></a>`;
                        } else if (data.type_name.toLowerCase() === 'video') {
                            contentHtml += `<video controls width="100%"><source src="${escapeHtml(data.file_path)}" type="video/mp4">Your browser does not support the video tag.</video>`;
                        } else { // Text
                            contentHtml += `<p>${escapeHtml(data.contentbody)}</p>`;
                        }

                        contentHtml += `</div>`;
                        modalBody.innerHTML = contentHtml;
                        modalTitle.textContent = `Review: ${escapeHtml(data.title)}`;
                    }
                    openModal();
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    modalBody.innerHTML = '<p style="color: red;">Could not load content. Please try again later.</p>';
                    openModal();
                });
        });
    });

    // Close modal events
    closeButton.addEventListener("click", closeModal);
    window.addEventListener("click", function(event) {
        if (event.target == modal) {
            closeModal();
        }
    });
    
    // Helper to prevent XSS
    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return unsafe
             .toString()
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }
});