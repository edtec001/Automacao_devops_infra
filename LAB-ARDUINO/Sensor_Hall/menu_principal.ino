void menu_principal(){



  // ----------------
  // Menu principal [0]
  // Escolhe entre Sinais básicos ou sinais com fase
  // ----------------

    
  while (submenu ==0)
  {
    digitalWrite(11, LOW);
    digitalWrite(12, LOW);
    digitalWrite(13, LOW);
    digitalWrite(3, LOW);



 while (opc_menu==0){
    lcd.setCursor(0, 0);
    lcd.print("[ Simples ]   ");
    lcd.setCursor(0, 1);
    lcd.print("Sincronizado    ");

    teclado();

    if (key == 5) {
      // PRESSIONOU SELECT
      // Vai pro Menu de sinais simples
      delay(250);
      lcd.clear();
      submenu=1;       // Vai pro menu de sinais simples
      key = 0;
      roda=0;
      opc_menu=99;
    }

    if (key == 2 || key == 3) {
      // UP ou DOWN
      delay(250);
      opc_menu=1;
      submenu =0;
      key = 0;
    }
    
    if (key == 4) {
      reset_default();
    }
 }




while (opc_menu==1){
    lcd.setCursor(0, 0);
    lcd.print("Simples    ");
    lcd.setCursor(0, 1);
    lcd.print("[ Sincronizado ]   ");

    teclado();

    if (key == 5) {
      // PRESSIONOU SELECT
      // Vai pro Menu de sinais complexos
      delay(250);
      lcd.clear();
      submenu=2;       // Vai pro menu de sinais complexos submenu=2
      key = 0;
      roda=0;
      opc_menu=99;
    }

    if (key == 2 || key == 3) {
      // UP ou DOWN   
      delay(250);   
      opc_menu=0;
      submenu =0;
      key = 0;      
    }
    
    if (key == 4) {
      reset_default();
    }
 }





 

    
  }
 
  // Fim Menu principal
  // -------------------

}
