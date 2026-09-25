# Cauções por prestação

As novas cauções são registadas em **Cauções → Adicionar Caução**. Cada entrada inicial é um recebimento real, com data e método livre. Cada prestação semanal tem um valor e uma Semana TVDE. A soma deve corresponder ao total em cêntimos.

## Dados e compatibilidade

- `driver_deposit_plans.driver_deposit_id` liga um plano à sua caução, de forma única.
- `driver_deposit_plan_items.kind` distingue `initial` de `weekly`.
- `driver_deposit_movements.source_key` identifica a entrada inicial, o débito previsto e a confirmação automática; a mesma confirmação é restaurada após anulação/revalidação.
- Os campos agregados antigos não geram prestações no circuito novo. As cauções anteriores mantêm o formulário e cálculo antigos. Não executar backfill para converter históricos implicitamente.
- Aplicar `php artisan migrate --path=database/migrations/2026_09_25_120000_link_deposit_installments.php` antes de usar o código atualizado. A migração só acrescenta campos e índices.

## Recebimentos

A validação semanal confirma apenas os lançamentos incluídos no relatório calculado pelo servidor. A confirmação e a conta corrente são gravadas na mesma transação. A consulta de relatórios não confirma pagamentos. Anular a validação remove apenas a confirmação automática, preservando entradas iniciais e pagamentos manuais.

Pagamentos manuais são atribuídos apenas ao plano da caução selecionada; havendo várias cauções para o mesmo motorista/empresa é obrigatória a seleção. Um adiantamento reduz o desconto ainda por cobrar. Recebimentos não têm impacto adicional no extrato. Devoluções e abatimentos usam o saldo efetivamente recebido no circuito novo.

Pagamentos recebidos e semanas validadas não são alteráveis pela grelha. Corrigir recebimentos através de movimentos. Pausar suspende os descontos futuros; fechar cancela prestações ainda não validadas. Reabrir uma caução cujo plano está pausado requer também reativar esse plano.

## Verificação

`php vendor/bin/phpunit tests/Feature/DriverDepositInstallmentsTest.php --do-not-cache-result`

Os testes usam exclusivamente SQLite em memória e executam as migrações relevantes. Cobrem o exemplo de 600 €, validação/revalidação/anulação, controlo de totais e cêntimos, proteção de históricos, pagamentos manuais, isolamento entre motoristas/empresas, devoluções, apresentação das grelhas e compatibilidade com o circuito antigo.
