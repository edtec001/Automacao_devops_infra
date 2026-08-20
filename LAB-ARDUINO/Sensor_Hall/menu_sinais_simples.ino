void menu_sinais_simples(){
  // ----------------
  // Menu de Sinais Simples (SUBMENU=1)
  // ----------------
  
  while (roda == 101 && submenu == 1)
  {
    lcd.setCursor(0, 0);
    lcd.print("60-2  [H/I]   ->");
    lcd_opcoes_basicas();     
    checa_opcao(); 
  }

  while (roda == 102 && submenu == 1)
  {
    lcd.setCursor(0, 0);
    lcd.print("44-4  [H/I]   ->");
    lcd_opcoes_basicas();     
    checa_opcao();    
  }

  while (roda == 103 && submenu == 1)
  {
    lcd.setCursor(0, 0);
    lcd.print("36-2  [H/I]   ->");
    lcd_opcoes_basicas();     
    checa_opcao();    
  }

  while (roda == 104 && submenu == 1)
  {
    lcd.setCursor(0, 0);
    lcd.print("36-1  [H/I]   ->");
    lcd_opcoes_basicas();     
    checa_opcao();    
  }
}

