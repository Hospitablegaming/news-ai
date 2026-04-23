function sendPrompt() {
  let analyseBtn = document.getElementById('analyseBtn');
  
  // Check if button is disabled (limit reached)
  if (analyseBtn.disabled) {
    alert('You have reached your monthly prompt limit. Please upgrade your plan or wait until next month.');
    return;
  }

  let prompt = document.getElementById('prompt').value;
  let publicShare = document.getElementById('public').checked;
  let resultDiv = document.getElementById('result');
  let resultContent = document.getElementById('resultContent');
  
  if (!prompt.trim()) {
    alert('Please enter some text to analyze');
    return;
  }
  
  analyseBtn.disabled = true;
  analyseBtn.textContent = '⏳ Analysing...';
  resultDiv.style.display = 'none';
  
  let formData = new URLSearchParams();
  formData.append('action', 'analyze');
  formData.append('prompt', prompt);
  formData.append('conversation_id', document.getElementById('conversation_id') ? document.getElementById('conversation_id').value : '0');
  if (publicShare) {
    formData.append('public', '1');
  }
  
  fetch('../controllers/PromptController.php?' + formData.toString(), {
    method: 'GET'
  })
  .then(res => res.text())
  .then(data => {
    resultContent.textContent = data;
    resultContent.style.whiteSpace = 'pre-wrap';
    resultDiv.style.display = 'block';
    resultDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    analyseBtn.disabled = false;
    analyseBtn.textContent = 'Analyse';
    // Reload page to show updated prompt count
    setTimeout(() => location.reload(), 2000);
  })
  .catch(error => {
    resultContent.textContent = 'Error: ' + error.message;
    resultContent.style.color = '#ff9999';
    resultContent.style.whiteSpace = 'pre-wrap';
    resultDiv.style.display = 'block';
    analyseBtn.disabled = false;
    analyseBtn.textContent = 'Analyse';
  });
}

// Delete a single prompt from a conversation
function deletePrompt(promptId) {
  if (confirm('Are you sure you want to delete this prompt?')) {
    fetch('../controllers/PromptController.php?action=delete&id=' + promptId + '&type=prompt', {
      method: 'GET'
    })
    .then(res => res.text())
    .then(data => {
      if (data === 'success') {
        location.reload();
      } else {
        alert('Error deleting prompt: ' + data);
      }
    })
    .catch(err => alert('Error: ' + err));
  }
}

// Delete an entire conversation
function deleteConversation(conversationId) {
  window.pendingDeleteId = conversationId;
  document.getElementById('deleteModal').style.display = 'flex';
}

function confirmDelete() {
  const conversationId = window.pendingDeleteId;
  document.getElementById('deleteModal').style.display = 'none';
  
  fetch('../controllers/PromptController.php?action=delete&id=' + conversationId + '&type=conversation', {
    method: 'GET'
  })
  .then(res => res.text())
  .then(data => {
    if (data === 'success') {
      location.reload();
    } else {
      alert('Error deleting conversation: ' + data);
    }
  })
  .catch(err => alert('Error: ' + err));
}

function cancelDelete() {
  document.getElementById('deleteModal').style.display = 'none';
  window.pendingDeleteId = null;
}

// Attach event listener when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  let analyseBtn = document.getElementById('analyseBtn');
  if (analyseBtn) {
    analyseBtn.addEventListener('click', sendPrompt);
  }

  // Allow Ctrl+Enter to submit from textarea
  let promptArea = document.getElementById('prompt');
  if (promptArea) {
    promptArea.addEventListener('keydown', function(e) {
      if (e.ctrlKey && e.key === 'Enter') {
        sendPrompt();
      }
    });
  }

});