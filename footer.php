</div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle submenu
        document.querySelectorAll('.has-submenu > a').forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const parent = this.parentElement;
                parent.classList.toggle('open');
            });
        });
        
        // Set active menu based on current page
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.sidebar-menu a').forEach(function(link) {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
                
                // If it's in a submenu, open the parent
                const parent = link.closest('.has-submenu');
                if (parent) {
                    parent.classList.add('open');
                }
            }
        });
    </script>
</body>
</html>