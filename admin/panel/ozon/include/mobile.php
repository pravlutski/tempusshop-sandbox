<div class="c-menu-btn-op position-fixed top-5 start-0" style="">
  <svg width="20px" height="20px" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" fill="none" style="margin-top:30px">
    <path fill="#000000" fill-rule="evenodd" d="M19 4a1 1 0 01-1 1H2a1 1 0 010-2h16a1 1 0 011 1zm0 6a1 1 0 01-1 1H2a1 1 0 110-2h16a1 1 0 011 1zm-1 7a1 1 0 100-2H2a1 1 0 100 2h16z"/>
  </svg>
</div>

<div class="c-menu-btn-cls position-fixed top-5 end-0" style="">
  <svg width="20px" height="20px" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" fill="none" style="margin-top:30px">
    <path fill="#000000" fill-rule="evenodd" d="M19 4a1 1 0 01-1 1H2a1 1 0 010-2h16a1 1 0 011 1zm0 6a1 1 0 01-1 1H2a1 1 0 110-2h16a1 1 0 011 1zm-1 7a1 1 0 100-2H2a1 1 0 100 2h16z"/>
  </svg>
</div>

<style media="screen">
.c-menu-btn-op{
  top: 400px;
  display: none;
  border-top-right-radius: 12px;
  border-bottom-right-radius: 12px;
  height: 80px;
  width: 25px;
  border-right: 1px solid black;
  background-color: white;
}
.c-menu-btn-cls{
  top: 400px;
  display: none;
  border-top-left-radius: 12px;
  border-bottom-left-radius: 12px;
  height: 80px;
  width: 25px;
  border-left: 1px solid black;
  background-color: white;
  z-index: 999;
}
@media (max-width: 867px){
  #sidebarMenu{
    height: 100%;
    margin-top: -50px;
  }
  .nav-item{
    padding: 10px 0 10px 0;
    /* border-top: 1px solid rgba(0,0,0,0.25); */
    border-bottom: 1px solid rgba(0,0,0,0.25);
  }
  .c-menu-btn-op{
    display: block !important;
  }
  .header-return-btn{
    display: none;
  }
}
</style>

<script type="text/javascript">
$(document).on('click','.c-menu-btn-op', function(){
  $('#sidebarMenu').show();
  $('.c-menu-btn-cls').show();
})
$(document).on('click','.c-menu-btn-cls', function(){
  $('#sidebarMenu').hide();
  $('.c-menu-btn-cls').hide();
})
</script>
