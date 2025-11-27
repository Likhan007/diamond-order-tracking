(function($){
    var debounce = function(fn, delay){
      var t;
      return function(){
        var args = arguments;
        clearTimeout(t);
        t = setTimeout(function(){ fn.apply(null, args); }, delay);
      };
    };
  
    function getOrderIdFromQuery() {
      var match = window.location.search.match(/id=(\d+)/);
      return match ? parseInt(match[1], 10) : 0;
    }

    function initSearch(){
      var $input = $('#dot-search-input');
      var $results = $('#dot-search-results');
      if (!$input.length) return;
  
      $input.on('input', debounce(function(){
        var q = $input.val().trim();
        if (!q) { $results.empty().hide(); return; }
        $.post(dot_search_ajax.url, {
          action: 'dot_search_orders',
          nonce: dot_search_ajax.nonce,
          q: q,
          scope: dot_search_ajax.is_admin ? 'admin' : 'client'
        }, function(resp){
          if (!resp || !resp.success) { $results.html('<div class="dot-search-empty">No results</div>').show(); return; }
          var html = '<div class="dot-search-list">';
          resp.data.results.forEach(function(r){
            html += '<a class="dot-search-item" href="' + window.location.origin + '/order/?id=' + r.id + '">';
            html += '<div class="dot-search-code">' + (r.order_code || ('ID-'+r.id)) + '</div>';
            var metaParts = [];
            if (r.style_name) metaParts.push(r.style_name);
            if (dot_search_ajax.is_admin) {
              if (r.client_email) metaParts.push(r.client_email);
            } else if (dot_search_ajax.client_name) {
              metaParts.push(dot_search_ajax.client_name);
            }
            html += '<div class="dot-search-meta">' + metaParts.join(' • ') + '</div>';
            html += '</a>';
          });
          html += '</div>';
          $results.html(html).show();
        }, 'json');
      }, 300));
  
      $(document).on('click', function(e){
        if (!$(e.target).closest('#dot-search-results, #dot-search-input').length) {
          $results.hide();
        }
      });
    }
  
    function initComments(){
      var $area = $('#dot-comments-area');
      if (!$area.length) return;

      var $submit = $('#dot-comment-submit');
      if ($submit.length) {
        var $txt = $('#dot-comment-text');
        // Use off().on() to prevent duplicate handlers
        $submit.off('click.dotCommentSubmit').on('click.dotCommentSubmit', function(e){
          e.preventDefault();
          e.stopPropagation();
          
          var comment = $txt.val().trim();
          if (!comment) return;
          
          // Prevent double-submission
          if ($submit.hasClass('submitting-in-progress')) return;
          $submit.addClass('submitting-in-progress').prop('disabled', true).text('Posting...');
          
          var orderId = getOrderIdFromQuery();
          if (!orderId) {
            alert('Could not find order ID.');
            $submit.removeClass('submitting-in-progress').prop('disabled', false).text('Post comment');
            return;
          }
          
          $.ajax({
            url: dot_search_ajax.url,
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'dot_add_comment',
              nonce: dot_search_ajax.nonce,
              order_id: orderId,
              comment: comment
            },
            success: function(resp) {
              console.log('Comment submission response:', resp);
              
              // Check response structure
              if (!resp) {
                console.error('No response received');
                alert('No response from server. Please refresh the page.');
                return;
              }
              
              if (resp.success && resp.data && resp.data.html) {
                var updateStartTime = Date.now();
                
                // Get the comments HTML and element
                var commentsHtml = resp.data.html;
                var commentsArea = document.getElementById('dot-comments-area');
                
                // Update IMMEDIATELY using native DOM (fastest method)
                if (commentsArea) {
                  // Ensure element is visible
                  commentsArea.style.display = '';
                  commentsArea.style.visibility = 'visible';
                  
                  // Clear input first
                  $txt.val('');
                  
                  // Update HTML - this should be instant
                  commentsArea.innerHTML = commentsHtml;
                  
                  // Force browser to render immediately
                  void commentsArea.offsetHeight;
                  
                  // Scroll to show new comment
                  var firstComment = commentsArea.querySelector('.dot-comment');
                  if (firstComment) {
                    firstComment.scrollIntoView({ behavior: 'auto', block: 'start' });
                  } else {
                    commentsArea.scrollTop = 0;
                  }
                  
                  var updateTime = Date.now() - updateStartTime;
                  console.log('Comments area updated in ' + updateTime + 'ms via native DOM');
                  
                  // Double-check: verify the HTML was actually set
                  if (commentsArea.innerHTML.length !== commentsHtml.length) {
                    console.warn('HTML length mismatch! Expected:', commentsHtml.length, 'Got:', commentsArea.innerHTML.length);
                  }
                } else {
                  // Fallback to jQuery
                  var $commentsArea = $('#dot-comments-area');
                  if ($commentsArea.length) {
                    $txt.val('');
                    $commentsArea.html(commentsHtml);
                    $commentsArea.scrollTop(0);
                    var updateTime = Date.now() - updateStartTime;
                    console.log('Comments area updated in ' + updateTime + 'ms via jQuery fallback');
                  } else {
                    console.error('Comments area element not found!');
                    alert('Comment posted but display update failed. Please refresh.');
                  }
                }
              } else {
                var errorMsg = (resp && resp.data) ? (typeof resp.data === 'string' ? resp.data : JSON.stringify(resp.data)) : 'Unknown error';
                console.error('Comment submission error - unexpected response format:', resp);
                alert('Failed to post comment: ' + errorMsg);
              }
            },
            error: function(xhr, status, error) {
              console.error('AJAX error:', status, error, xhr.responseText);
              alert('Failed to post comment. Please check your connection and try again.');
            },
            complete: function() {
              $submit.removeClass('submitting-in-progress').prop('disabled', false).text('Post comment');
            }
          });
        });
      }

      // periodic refresh
      setInterval(function(){
        var orderId = getOrderIdFromQuery();
        if (!orderId) return;
        $.post(dot_search_ajax.url, { action: 'dot_get_comments', nonce: dot_search_ajax.nonce, order_id: orderId }, function(resp){
          if (resp && resp.success) $('#dot-comments-area').html(resp.data.html);
        }, 'json');
      }, 45000);
    }

    // Delete comment handler (admin only) - SINGLE global handler, never re-bound
    $(document).on('click', '.dot-comment-delete', function(e){
      e.preventDefault();
      e.stopPropagation();
      
      // Only proceed if admin
      if (!dot_search_ajax || !dot_search_ajax.is_admin) return;
      
      var $btn = $(this);
      
      // Prevent double-clicks
      if ($btn.hasClass('deleting-in-progress')) return;
      $btn.addClass('deleting-in-progress');
      
      var idx = $btn.data('index');
      if (typeof idx === 'undefined' || idx === null) {
        console.error('Delete button missing index');
        $btn.removeClass('deleting-in-progress');
        return;
      }
      
      // Single confirmation dialog
      if (!confirm('Delete this comment?')) {
        $btn.removeClass('deleting-in-progress');
        return;
      }
      
      var orderId = getOrderIdFromQuery();
      if (!orderId) {
        alert('Could not find order ID.');
        $btn.removeClass('deleting-in-progress');
        return;
      }
      
      $btn.prop('disabled', true).css('opacity', '0.5');
      $.post(dot_search_ajax.url, {
        action: 'dot_delete_comment',
        nonce: dot_search_ajax.nonce,
        order_id: orderId,
        index: idx
      }, function(resp){
        if (resp && resp.success && resp.data && resp.data.html) {
          $('#dot-comments-area').html(resp.data.html);
        } else {
          alert('Failed to delete comment: ' + (resp && resp.data ? resp.data : 'Unknown error'));
        }
      }, 'json').fail(function(xhr, status, error){
        console.error('Delete failed:', status, error);
        alert('Failed to delete comment. Please try again.');
      }).always(function(){
        $btn.prop('disabled', false).css('opacity', '1').removeClass('deleting-in-progress');
      });
    });
  
    $(function(){ 
      initSearch(); 
      initComments();
    });
  })(jQuery);
  