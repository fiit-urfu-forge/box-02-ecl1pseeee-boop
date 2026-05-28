using CW3.Utils;

namespace CW3.Task23;

public class Tests23
{
    public static void Test()
    {
        Console.WriteLine("################ Задача 23. Железные дороги ################\n");

        // Тест 1: пример из условия
        RunTest(3, new[] { "RB", "R" }, "NO", "Пример 1 из условия");

        // Тест 2: пример из условия
        RunTest(4, new[] { "BBB", "RB", "B" }, "YES", "Пример 2 из условия");

        // Тест 3: пример из условия
        RunTest(5, new[] { "RRRB", "BRR", "BR", "R" }, "NO", "Пример 3 из условия");

        // Тест 4: минимальная карта с одной дорогой типа R
        RunTest(2, new[] { "R" }, "YES", "Два города, дорога R");

        // Тест 5: минимальная карта с одной дорогой типа B
        RunTest(2, new[] { "B" }, "YES", "Два города, дорога B");

        // Тест 6: все дороги одного типа — пересечения быть не может
        RunTest(3, new[] { "RR", "R" }, "YES", "Все дороги типа R");

        // Тест 7: дублирующий маршрут разных типов
        RunTest(4, new[] { "RBR", "RR", "R" }, "NO", "Маршрут достижим и по R, и по B");

        // Тест 8: все дороги типа B
        RunTest(4, new[] { "BBB", "BB", "B" }, "YES", "Все дороги типа B");

        // Тест 9: карта побольше, целиком из дорог R
        RunTest(6, new[] { "RRRRR", "RRRR", "RRR", "RR", "R" }, "YES", "Шесть городов, все дороги R");

        // Тест 10: пересечение достижимостей сразу по нескольким парам
        RunTest(4, new[] { "RBB", "RB", "R" }, "NO", "Несколько дублирующих пар");
    }

    private static void RunTest(int n, string[] rows, string expected, string testName)
    {
        Console.WriteLine($"Тест: {testName}");
        try
        {
            var profileResult = AlgorithmProfiler.Profile("Железные дороги", () =>
                Task23.Solution(n, rows));

            string result = profileResult.Result!.FirstOrDefault() ?? "";

            Console.WriteLine($"Входные данные: n = {n}, карта = [{string.Join(", ", rows)}]");
            if (result == expected)
                Console.WriteLine("Тест пройден успешно!");
            else
                Console.WriteLine($"ПРОВАЛЕНО! Ожидалось: {expected}, получено: {result}");

            Console.WriteLine(profileResult.ToString());
            Console.WriteLine("════════════════════════════════════════\n");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"ERROR: {ex.Message}");
        }
    }
}
