<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>

<script>
window.onload = function() {
    const printBtn = document.getElementById('print-btn');
    
    // Фокус + обработка нажатия Enter
    printBtn.focus();
    printBtn.addEventListener('keypress', (e) => {
        if(e.key === 'Enter') window.print();
    });
    window.print();
    // Клик мышкой
    printBtn.addEventListener('click', () => window.print());
};
</script>

<button id="print-btn" autofocus>Печать (Enter)</button>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
