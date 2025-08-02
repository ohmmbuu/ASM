<br>
<br>
<br>
</div>
</div>
</div>
<footer class="bg-white p-4 absolute bottom-0 left-0 w-full border-t mt-4">
    <div class="flex justify-between items-center text-gray-600 container mx-auto px-4">
        <p class="text-sm">&copy; 2025 บันทึกคะแนนเก็บของนักเรียน</p>
        <div class="flex items-center space-x-4">
            <p class="text-sm hidden sm:block">ผู้พัฒนาโดย : <a href="http://www.kruwirat.com" target="_blank" class="hover:text-blue-500">www.kruwirat.com</a> || tel : 095-602-9737</p>
            <a href="http://www.kruwirat.com" target="_blank">
                <img src="https://www.i-pic.info/i/krVq1004512.jpg" alt="Developer Logo" class="w-10 h-10 rounded-full shadow">
            </a>
        </div>
    </div>
</footer>

<script>
    function confirmDelete(url) {
        Swal.fire({
            title: 'แน่ใจหรือไม่?',
            text: "คุณต้องการลบข้อมูลนี้ใช่หรือไม่!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        })
    }
</script>

</body>

</html>