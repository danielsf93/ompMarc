<h1>ompMarc</h1>

Projeto de exportação de arquivos record marc para coleta de produção da ABCD USP. 
<br> Baseado em https://coletaprod.aguia.usp.br/z3950.php?isbn=&sysno=&title=the+urban+crisis&author=&year=&btn_submit= <br><br>

/plugins/importexport/ompMarc<br><br>

Este plugin exporta informações de livros OMP em formato .mrk. 

<h3>Tutorial:</h3>
-Acesse o plugin em 'ferramentas > Importar/Exportar > ompMarc'.<br>
![image](https://github.com/danielsf93/ompMarc/assets/114300053/1ec5693e-7149-4070-bf4f-7d5a8b345b9e) <br>
-Selecione o livro e clique em 'Exportar Submissão'.<br>
-Será baixado um arquivo 'omp.mrk'.<br>
![image](https://github.com/danielsf93/ompMarc/assets/114300053/ca5f00b8-0919-49d7-9458-55a04a0f6f32) <br>
-Crie a seguinte pasta (windows) 'C:\AL500_23\Catalog\ConvertIn\omp'.<br>
-Mova/recorte os arquivos omp.mrk da pasta 'Downloads' para 'C:\AL500_23\Catalog\ConvertIn\omp'.<br>
-Abra o programa MarcEdit 7.6.16.<br>
-Selecione 'Tools > MARC Processing Tolls > Batch Process Records', ou utilize o comando 'Cltrl+Shift+B'.<br>
-Em 'Source Directory' selecione a pasta 'C:\AL500_23\Catalog\ConvertIn\omp'. Em 'Function' selecione 'From Mnemonic to MARC' e clique em 'Process'.<br>
![image](https://github.com/danielsf93/ompMarc/assets/114300053/68c0468b-c37d-406c-b443-2ce043b857ad) <br>
-Aparecerá a mensagem 'X files have been generated'. Os arquivos da pasta 'C:\AL500_23\Catalog\ConvertIn\omp' foram convertidos para o formato .mrc dentro da pasta 'C:\AL500_23\Catalog\ConvertIn\omp\processed_files'.<br>
-Agora a rotina de Importação de Títulos do Aleph pode ser seguida normalmente. Lembre-se de que os 'Arquivos de Entrada' estarão na pasta 'C:\AL500_23\Catalog\ConvertIn\omp\processed_files' e a 'Rotina de Conversão' deve ser 'MARC'.<br><br>

Neste repositório, na pasta exemplos existem arquivos .mrk para teste.


<br><br>
A fazer:
Verificar com equipe técnica se a formatação obtida em .mrc está correta. Verificar se o esquema de pastas é o melhor, vizando que haverá uma adaptação do mesmo plugin para OJS, e criar mais pastas demtro de "Convertin" pode não ser uma boa ideia.








