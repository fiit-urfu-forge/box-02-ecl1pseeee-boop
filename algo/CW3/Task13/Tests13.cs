using CW3.Utils;

namespace CW3.Task13;

public class Tests13
{
    public static void Test()
    {
        Console.WriteLine("################ Задача 13. Игра в фишки ################\n");

        // Тест 1: пример из условия
        RunTest(1, "Second", "Пример: N = 1");

        // Тест 2: первый берёт единственную фишку
        RunTest(2, "First", "N = 2");

        // Тест 3: пример из условия
        RunTest(5, "First", "Пример: N = 5");

        // Тест 4: проигрышная позиция
        RunTest(6, "Second", "N = 6 — проигрышная позиция");

        // Тест 5: пример из условия
        RunTest(10, "Second", "Пример: N = 10");

        // Тест 6: выигрышная позиция в середине
        RunTest(15, "First", "N = 15");

        // Тест 7: проигрышная позиция
        RunTest(19, "Second", "N = 19 — проигрышная позиция");

        // Тест 8: выигрышная позиция
        RunTest(20, "First", "N = 20");

        // Тест 9: значение среднего размера
        RunTest(100, "First", "N = 100");

        // Тест 10: максимальное значение по условию
        RunTest(1000000, "First", "N = 1 000 000 — граница условия");
    }

    private static void RunTest(long n, string expected, string testName)
    {
        Console.WriteLine($"Тест: {testName}");
        try
        {
            var profileResult = AlgorithmProfiler.Profile("Игра в фишки", () =>
                Task13.Solution(n));

            string result = profileResult.Result!.FirstOrDefault() ?? "";

            Console.WriteLine($"Входные данные: N = {n}");
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
