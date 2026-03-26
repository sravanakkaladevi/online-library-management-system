<?php if(!empty($_SESSION['login']) && !empty($_SESSION['stdid'])){ ?>
    <div class="book-chatbot" id="bookChatbot">
        <button type="button" class="book-chatbot__toggle" id="bookChatbotToggle" aria-expanded="false" aria-controls="bookChatbotPanel">
            Book Chatbot
        </button>
        <div class="book-chatbot__panel" id="bookChatbotPanel" aria-hidden="true">
            <div class="book-chatbot__header">
                <h4>Find Similar Books</h4>
                <button type="button" class="book-chatbot__close" id="bookChatbotClose" aria-label="Close chatbot">&times;</button>
            </div>
            <p class="book-chatbot__intro">Copy a paragraph from any book and paste it here. The chatbot will recommend books from your library collection based on the content.</p>
            <form id="bookChatbotForm">
                <div class="form-group">
                    <label for="bookChatbotContent">Paste book content</label>
                    <textarea id="bookChatbotContent" name="content" class="form-control" rows="6" placeholder="Example: paste a paragraph about programming, finance, science, or any topic from a book..."></textarea>
                </div>
                <div class="book-chatbot__actions">
                    <button type="submit" class="btn btn-primary">Recommend Books</button>
                    <button type="button" class="btn btn-default" id="bookChatbotPaste">Paste</button>
                </div>
            </form>
            <div class="book-chatbot__status" id="bookChatbotStatus">Paste a few sentences to get started.</div>
            <div class="book-chatbot__results" id="bookChatbotResults"></div>
        </div>
    </div>
    <script type="text/javascript">
    (function () {
        var widget=document.getElementById('bookChatbot');
        if(!widget){
            return;
        }

        var toggle=document.getElementById('bookChatbotToggle');
        var closeBtn=document.getElementById('bookChatbotClose');
        var panel=document.getElementById('bookChatbotPanel');
        var form=document.getElementById('bookChatbotForm');
        var textarea=document.getElementById('bookChatbotContent');
        var statusBox=document.getElementById('bookChatbotStatus');
        var resultsBox=document.getElementById('bookChatbotResults');
        var pasteBtn=document.getElementById('bookChatbotPaste');

        if(!toggle || !closeBtn || !panel || !form || !textarea || !statusBox || !resultsBox || !pasteBtn){
            return;
        }

        function hasOpenClass() {
            return /(^|\s)book-chatbot--open(\s|$)/.test(widget.className);
        }

        function setOpenState(isOpen) {
            if(isOpen){
                widget.className='book-chatbot book-chatbot--open';
                toggle.setAttribute('aria-expanded','true');
                panel.setAttribute('aria-hidden','false');
            } else {
                widget.className='book-chatbot';
                toggle.setAttribute('aria-expanded','false');
                panel.setAttribute('aria-hidden','true');
            }
        }

        function setStatus(message, isError) {
            statusBox.className=isError ? 'book-chatbot__status book-chatbot__status--error' : 'book-chatbot__status';
            if(typeof statusBox.textContent!=='undefined'){
                statusBox.textContent=message;
            } else {
                statusBox.innerText=message;
            }
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[char];
            });
        }

        function renderResults(books) {
            if(!books || !books.length){
                resultsBox.innerHTML='';
                return;
            }

            var html='';
            for(var i=0;i<books.length;i++){
                var book=books[i];
                html+='<div class="book-chatbot__card">';
                html+='<div class="book-chatbot__card-top">';
                html+='<span class="book-chatbot__match">'+escapeHtml(book.matchPercent)+'% match</span>';
                html+='</div>';
                html+='<h5>'+escapeHtml(book.title)+'</h5>';
                html+='<p><strong>Author:</strong> '+escapeHtml(book.author)+'</p>';
                html+='<p><strong>Category:</strong> '+escapeHtml(book.category)+'</p>';
                html+='<p><strong>Available:</strong> '+escapeHtml(book.availableQty)+'</p>';
                html+='<p><strong>Rating:</strong> '+escapeHtml(Number(book.averageRating).toFixed(1))+' / 5 ('+escapeHtml(book.reviewCount)+' reviews)</p>';
                html+='<a class="btn btn-info btn-sm" href="'+escapeHtml(book.detailsUrl)+'">Open Details</a>';
                html+='</div>';
            }
            resultsBox.innerHTML=html;
        }

        toggle.onclick=function () {
            setOpenState(!hasOpenClass());
        };

        closeBtn.onclick=function () {
            setOpenState(false);
        };

        pasteBtn.onclick=function () {
            if(navigator.clipboard && navigator.clipboard.readText){
                navigator.clipboard.readText().then(function (text) {
                    if(text){
                        textarea.value=text;
                        setStatus('Clipboard text pasted. Click "Recommend Books" to search.', false);
                    } else {
                        setStatus('Clipboard is empty. Copy a paragraph first, then try again.', true);
                    }
                }).catch(function () {
                    setStatus('Clipboard access was blocked. Paste the content manually into the box.', true);
                });
            } else {
                setStatus('Clipboard paste is not supported here. Paste the content manually into the box.', true);
            }
        };

        form.onsubmit=function (event) {
            var content=textarea.value.replace(/^\s+|\s+$/g, '');
            var queryString=window.location.search || '';
            var match=queryString.match(/[?&]bookid=(\d+)/i);
            var excludeBookId=match ? match[1] : '';

            event.preventDefault();
            setOpenState(true);
            resultsBox.innerHTML='';

            if(content.length<25){
                setStatus('Paste at least a few sentences so the chatbot can match the topic.', true);
                return false;
            }

            setStatus('Finding matching books...', false);

            var requestBody='content='+encodeURIComponent(content);
            if(excludeBookId){
                requestBody+='&exclude_bookid='+encodeURIComponent(excludeBookId);
            }

            var xhr=new XMLHttpRequest();
            xhr.open('POST', 'chatbot-recommend.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.onreadystatechange=function () {
                if(xhr.readyState!==4){
                    return;
                }

                var response=null;
                try {
                    response=JSON.parse(xhr.responseText);
                } catch (error) {
                    response=null;
                }

                if(xhr.status!==200 || !response){
                    setStatus('The chatbot could not load recommendations right now. Please try again.', true);
                    return;
                }

                setStatus(response.message || 'Recommendations loaded.', !response.success);
                renderResults(response.books || []);
            };
            xhr.send(requestBody);
            return false;
        };
    })();
    </script>
<?php } ?>
