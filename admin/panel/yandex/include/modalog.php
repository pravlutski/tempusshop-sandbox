<div class="modal-background">
  <div class="modal-window">
    <div class="modal-head">
      <h4>Лог работы модуля</h4>
    </div>
    <hr>
    <div class="modal-body log-body">

    </div>
  </div>
</div>

<style media="screen">
/* Modal window */
.modal-background{
  display: none;
  width: 100%;
  height: 100%;
  top: 0;
  left: 0;
  position: fixed;
  background-color: rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(5px);
  z-index: 998;
  overflow-y: auto;
}
.modal-window{
  display: flex;
  padding: 40px;
  border-radius: 6px;
  flex-direction: column;
  /* height: 580px; */
  height: 80%;
  width: 73%;
  background-color: white;
  margin: 5% auto auto auto;
  z-index: 999;
}

.modal-body{
  height: 100%;
  overflow-y: auto;
  background-color: #f5f5f5;
  border-radius: 6px;
}
</style>
